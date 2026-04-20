<?php
/**
 * QuickMLS — Client View (Shared Property Page)
 * No search, no admin controls, only Chip & Kim agent info
 * Performs search SERVER-SIDE so no auth is needed on the client.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/api.php';
require_once __DIR__ . '/lib/geocode.php';
require_once __DIR__ . '/lib/photos.php';

$token = trim($_GET['t'] ?? '');
if (!$token) { http_response_code(404); echo 'Invalid link.'; exit; }

$db   = getDb();
$stmt = $db->prepare("SELECT address, hero_listing_key, radius_miles, filter_types, filter_subtypes FROM shares WHERE token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$share  = $result->fetch_assoc();
$stmt->close();

if (!$share) { http_response_code(404); echo 'This link has expired or is invalid.'; exit; }

$shareAddress    = $share['address'];
$heroListingKey  = $share['hero_listing_key'] ?? '';
$shareRadius     = (float)$share['radius_miles'];
$shareFilterTypes    = json_decode($share['filter_types'] ?? 'null', true);
$shareFilterSubTypes = json_decode($share['filter_subtypes'] ?? 'null', true);

// ── Do the search server-side ──
$searchData = null;
try {
    // Reuse the same search logic from api/search.php
    $selectFields = implode(',', [
        'ListingKey','ListingId','StandardStatus',
        'ListPrice','OriginalListPrice','ClosePrice','CloseDate',
        'StreetNumber','StreetName','StreetSuffix','StreetDirPrefix','StreetDirSuffix',
        'UnitNumber','City','StateOrProvince','PostalCode','CountyOrParish',
        'Latitude','Longitude',
        'BedroomsTotal','BathroomsTotalInteger','BathroomsFull','BathroomsHalf',
        'LivingArea','LotSizeAcres','LotSizeSquareFeet','YearBuilt','GarageSpaces',
        'StoriesTotal','Flooring','Heating','Cooling','Roof','PoolPrivateYN',
        'PropertyType','PropertySubType',
        'PublicRemarks','SyndicationRemarks',
        'Appliances','InteriorFeatures','ExteriorFeatures',
        'ParkingFeatures','LaundryFeatures','FireplaceFeatures',
        'WaterSource','Sewer','Electric',
        'FoundationDetails','ArchitecturalStyle','BuildingAreaTotal',
        'CommonWalls','ConstructionMaterials','DirectionFaces',
        'PatioAndPorchFeatures','SecurityFeatures','View',
        'WindowFeatures','Fencing',
        'AssociationFee','AssociationFeeFrequency',
        'DaysOnMarket','CumulativeDaysOnMarket','ModificationTimestamp','ListingContractDate',
        'TaxAnnualAmount','TaxAssessedValue',
    ]);

    require_once __DIR__ . '/api/search_helpers.php';

    $addrParts = parseAddressString($shareAddress);
    $geo = geocodeAddress($shareAddress);

    if ($geo) {
        $subject = findSubjectProperty($addrParts, $geo, $selectFields);

        // If we have a specific hero ListingKey, make sure we use it
        if ($heroListingKey) {
            // Try to find the hero by ListingKey in subject or via direct lookup
            if (!$subject || ($subject['ListingKey'] ?? '') !== $heroListingKey) {
                // Direct lookup by ListingKey
                try {
                    $heroResult = trestleGet('Property', [
                        '$filter'  => "ListingKey eq '" . addslashes($heroListingKey) . "'",
                        '$select'  => $selectFields,
                        '$top'     => 1,
                    ]);
                    $heroProps = $heroResult['value'] ?? [];
                    if (!empty($heroProps)) {
                        $subject = $heroProps[0];
                        // Use hero's coordinates for geocoding if available
                        if ($subject['Latitude'] && $subject['Longitude']) {
                            $geo['lat'] = (float)$subject['Latitude'];
                            $geo['lng'] = (float)$subject['Longitude'];
                        }
                    }
                } catch (Exception $e) {
                    // Fall back to address-based subject
                }
            }
        }

        // Get ALL comps — frontend handles type filtering via checkboxes
        $comps = getComps($geo, $shareRadius, $selectFields);

        // Photos
        $allKeys = [];
        if ($subject) $allKeys[] = $subject['ListingKey'];
        foreach ($comps as $c) $allKeys[] = $c['ListingKey'];
        $allKeys = array_unique(array_filter($allKeys));
        $photos = batchGetAllPhotos($allKeys);

        $photoBaseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/photo.php?url=';

        if ($subject) {
            $rawPhotos = $photos[$subject['ListingKey']] ?? [];
            $rawPhotos = array_values(array_filter($rawPhotos, fn($u) => $u && strlen($u) > 5));
            $subject['_photos'] = array_map(fn($u) => $photoBaseUrl . urlencode($u), $rawPhotos);
        }
        foreach ($comps as &$comp) {
            $rawPhotos = $photos[$comp['ListingKey'] ?? ''] ?? [];
            $rawPhotos = array_values(array_filter($rawPhotos, fn($u) => $u && strlen($u) > 5));
            $comp['_photos'] = array_map(fn($u) => $photoBaseUrl . urlencode($u), $rawPhotos);
            $cLat = (float)($comp['Latitude'] ?? 0);
            $cLng = (float)($comp['Longitude'] ?? 0);
            if ($cLat && $cLng) {
                $comp['_distance'] = haversineDistance($geo['lat'], $geo['lng'], $cLat, $cLng);
                $comp['_distanceFt'] = round($comp['_distance'] * 5280);
            }
        }
        unset($comp);
        usort($comps, fn($a, $b) => ($a['_distance'] ?? 999) <=> ($b['_distance'] ?? 999));

        $searchData = [
            'success'         => true,
            'geocoded'        => $geo,
            'subject'         => $subject,
            'comps'           => $comps,
            'compCount'       => count($comps),
            'address'         => $shareAddress,
            'radius_miles'    => $shareRadius,
            'filter_types'    => $shareFilterTypes,
            'filter_subtypes' => $shareFilterSubTypes,
        ];
    }
} catch (Exception $e) {
    // Search failed — page will show error
}

$v = time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Details — <?= htmlspecialchars($shareAddress) ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="css/style.css?v=<?=$v?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div id="loader" class="loader">
    <div class="loader-spinner"></div>
    <div class="loader-text">Loading property details...</div>
</div>

<div class="app">

    <header class="header">
        <div class="header-brand">
            <span class="header-icon">&#9889;</span>
            <h1>QuickMLS</h1>
        </div>
        <p class="header-tagline">Property details prepared for you by <?= htmlspecialchars(TEAM_NAME) ?></p>

        <div class="theme-toggle-wrap">
            <label class="theme-toggle" title="Toggle light/dark mode">
                <input type="checkbox" id="themeToggle">
                <span class="toggle-slider">
                    <span class="toggle-icon sun">&#9788;</span>
                    <span class="toggle-icon moon">&#9790;</span>
                </span>
            </label>
        </div>
    </header>

    <div id="results" class="results hidden">
        <div id="heroSection" class="hero-section">
            <div class="hero-carousel-wrap">
                <div id="heroCarousel" class="hero-carousel"></div>
                <button id="carouselLeft" class="carousel-arrow carousel-left">&#8249;</button>
                <button id="carouselRight" class="carousel-arrow carousel-right">&#8250;</button>
                <div id="carouselCounter" class="carousel-counter">1 / 1</div>
                <div id="heroStatusBadge" class="hero-status-badge">Active</div>
            </div>
            <div class="hero-body">
                <div class="hero-address-row">
                    <div>
                        <h2 id="heroAddress" class="hero-address"></h2>
                        <div id="heroCityLine" class="hero-city-line"></div>
                    </div>
                    <div class="hero-price-block">
                        <div id="heroPrice" class="hero-price"></div>
                        <div id="heroPriceSqft" class="hero-price-sqft"></div>
                    </div>
                </div>
                <div class="hero-stats-bar">
                    <div class="hero-stat"><span id="heroBeds">—</span><small>Beds</small></div>
                    <div class="hero-stat"><span id="heroBaths">—</span><small>Baths</small></div>
                    <div class="hero-stat"><span id="heroSqft">—</span><small>Sq Ft</small></div>
                    <div class="hero-stat"><span id="heroYear">—</span><small>Year Built</small></div>
                    <div class="hero-stat"><span id="heroLot">—</span><small>Lot</small></div>
                    <div class="hero-stat"><span id="heroGarage">—</span><small>Garage</small></div>
                    <div class="hero-stat"><span id="heroStories">—</span><small>Stories</small></div>
                    <div class="hero-stat"><span id="heroDom">—</span><small>DOM</small></div>
                </div>
                <div id="heroTags" class="hero-tags"></div>
                <div id="heroAgents" class="hero-agents"></div>
                <div id="heroDetailsGrid" class="hero-details-grid"></div>
                <div id="heroPublicRemarks" class="hero-remarks hidden">
                    <h4>Public Remarks</h4>
                    <p id="heroPublicRemarksText"></p>
                </div>
                <div id="heroMeta" class="hero-meta"></div>
            </div>
        </div>

        <div class="map-comps-section">
            <h3 class="section-title">
                Nearby Comparable Properties
                <span id="compCount" class="comp-count"></span>
            </h3>
            <div id="map" class="map-container"></div>
            <div id="compFilters" class="comp-filters hidden"></div>
            <div id="compsList" class="comps-list"></div>
        </div>
    </div>

    <div id="noResults" class="no-results hidden">
        <div class="no-results-icon">&#128270;</div>
        <p id="noResultsMsg">No MLS listing found for this address.</p>
    </div>

    <div class="client-agent-footer">
        <div class="client-agent-photo">
            <img src="img/chip-and-kim.png" alt="Chip &amp; Kim McAllister">
        </div>
        <div class="client-agent-cards">
            <div class="client-agent-card">
                <div class="client-agent-name"><?= htmlspecialchars(AGENT_NAME) ?></div>
                <div class="client-agent-title"><?= htmlspecialchars(AGENT_TITLE) ?> · DRE# <?= htmlspecialchars(AGENT_LICENSE) ?></div>
                <div class="client-agent-contacts">
                    <a href="tel:<?= preg_replace('/\D/', '', AGENT_PHONE) ?>"><?= htmlspecialchars(AGENT_PHONE) ?></a>
                    <a href="mailto:<?= htmlspecialchars(AGENT_EMAIL) ?>"><?= htmlspecialchars(AGENT_EMAIL) ?></a>
                </div>
            </div>
            <div class="client-agent-card">
                <div class="client-agent-name"><?= htmlspecialchars(AGENT2_NAME) ?></div>
                <div class="client-agent-title"><?= htmlspecialchars(AGENT2_TITLE) ?> · DRE# <?= htmlspecialchars(AGENT2_LICENSE) ?></div>
                <div class="client-agent-contacts">
                    <a href="tel:<?= preg_replace('/\D/', '', AGENT2_PHONE) ?>"><?= htmlspecialchars(AGENT2_PHONE) ?></a>
                    <a href="mailto:<?= htmlspecialchars(AGENT2_EMAIL) ?>"><?= htmlspecialchars(AGENT2_EMAIL) ?></a>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="js/app.js?v=<?=$v?>"></script>
<script>
// Client mode — data is pre-loaded server-side, no API call needed
var CLIENT_MODE = true;
var PRELOADED_DATA = <?= json_encode($searchData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
var GOOGLE_MAPS_KEY = <?= json_encode(GOOGLE_MAPS_API_KEY) ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (PRELOADED_DATA && PRELOADED_DATA.success) {
        window.loadPreloadedData && window.loadPreloadedData(PRELOADED_DATA);
    } else {
        document.getElementById('loader').classList.add('hidden');
        document.getElementById('noResultsMsg').textContent = 'Could not load property data.';
        document.getElementById('noResults').classList.remove('hidden');
    }
});
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=places&callback=initPlaces"
    async defer>
</script>

</body>
</html>
