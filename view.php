<?php
/**
 * QuickMLS — Client View (Shared Property Page)
 * No search, no admin controls, only Chip & Kim agent info
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';

$token = trim($_GET['t'] ?? '');
if (!$token) { http_response_code(404); echo 'Invalid link.'; exit; }

$db   = getDb();
$stmt = $db->prepare("SELECT address, radius_miles FROM shares WHERE token = ?");
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();
$share  = $result->fetch_assoc();
$stmt->close();

if (!$share) { http_response_code(404); echo 'This link has expired or is invalid.'; exit; }

$shareAddress = $share['address'];
$shareRadius  = $share['radius_miles'];

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

    <!-- HEADER (client view — no search) -->
    <header class="header">
        <div class="header-brand">
            <span class="header-icon">&#9889;</span>
            <h1>QuickMLS</h1>
        </div>
        <p class="header-tagline">Property details prepared for you by <?= htmlspecialchars(TEAM_NAME) ?></p>

        <!-- Theme toggle -->
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

    <!-- RESULTS CONTAINER -->
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
            <div id="compsList" class="comps-list"></div>
        </div>

    </div>

    <!-- NO RESULTS -->
    <div id="noResults" class="no-results hidden">
        <div class="no-results-icon">&#128270;</div>
        <p id="noResultsMsg">No MLS listing found for this address.</p>
    </div>

    <!-- Agent Footer -->
    <div class="client-agent-footer">
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

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="js/app.js?v=<?=$v?>"></script>
<script>
// Client mode — auto-search with locked settings, no other agent info
var CLIENT_MODE = true;
var SHARE_ADDRESS = <?= json_encode($shareAddress) ?>;
var SHARE_RADIUS = <?= json_encode((float)$shareRadius) ?>;
var GOOGLE_MAPS_KEY = <?= json_encode(GOOGLE_MAPS_API_KEY) ?>;

// Auto-trigger search on load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('addressInput')?.remove();
    window.autoClientSearch && window.autoClientSearch(SHARE_ADDRESS, SHARE_RADIUS);
});
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=places&callback=initPlaces"
    async defer>
</script>

</body>
</html>
