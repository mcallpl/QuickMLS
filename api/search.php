<?php
/**
 * QuickMLS — Property Search API
 * POST: full_address → property details + comps + agent info
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_error_handler(function($severity, $message, $file, $line) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "PHP Error: $message"]);
    exit;
});

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/api.php';
require_once __DIR__ . '/../lib/geocode.php';
require_once __DIR__ . '/../lib/photos.php';

function jsonError(string $msg): void {
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('POST required');
}

$fullAddress = trim($_POST['full_address'] ?? '');
if (empty($fullAddress)) jsonError('Please enter an address.');

// ── Select fields — expanded for agent info ──
$selectFields = implode(',', [
    // Identity
    'ListingKey', 'ListingId', 'StandardStatus',
    // Price
    'ListPrice', 'OriginalListPrice', 'ClosePrice', 'CloseDate',
    // Address
    'StreetNumber', 'StreetName', 'StreetSuffix', 'StreetDirPrefix', 'StreetDirSuffix',
    'UnitNumber', 'City', 'StateOrProvince', 'PostalCode', 'CountyOrParish',
    // Coordinates
    'Latitude', 'Longitude',
    // Structure
    'BedroomsTotal', 'BathroomsTotalInteger', 'BathroomsFull', 'BathroomsHalf',
    'LivingArea', 'LotSizeAcres', 'LotSizeSquareFeet', 'YearBuilt', 'GarageSpaces',
    'StoriesTotal', 'Flooring', 'Heating', 'Cooling', 'Roof', 'PoolPrivateYN',
    // Type
    'PropertyType', 'PropertySubType',
    // Description
    'PublicRemarks',
    // Listing Agent
    'ListAgentFullName', 'ListAgentDirectPhone', 'ListAgentEmail',
    'ListAgentMlsId', 'ListAgentKey',
    // List Office
    'ListOfficeName', 'ListOfficePhone',
    // Buyer Agent
    'BuyerAgentFullName', 'BuyerAgentDirectPhone', 'BuyerAgentEmail',
    'BuyerAgentMlsId',
    // Buyer Office
    'BuyerOfficeName', 'BuyerOfficePhone',
    // Co-List Agent
    'CoListAgentFullName', 'CoListAgentDirectPhone', 'CoListAgentEmail',
    // HOA
    'AssociationFee', 'AssociationFeeFrequency',
    // Timing
    'DaysOnMarket', 'CumulativeDaysOnMarket', 'ModificationTimestamp', 'ListingContractDate',
    // Tax / Assessment
    'TaxAnnualAmount', 'TaxAssessedValue',
]);

try {
    // 1. Parse address parts
    $addrParts = parseAddressString($fullAddress);

    // 2. Geocode
    $geo = geocodeAddress($fullAddress);
    if (!$geo) jsonError("Could not locate \"$fullAddress\" — try adding city and state.");

    // 3. Find the subject property — exact address match in MLS
    $subject = findSubjectProperty($addrParts, $geo, $selectFields);

    // 4. Get comps within 1/8 mile
    $comps = getComps($geo, 0.125, $selectFields);

    // 5. Photos for subject + comps
    $allKeys = [];
    if ($subject) $allKeys[] = $subject['ListingKey'];
    foreach ($comps as $c) $allKeys[] = $c['ListingKey'];
    $allKeys = array_unique(array_filter($allKeys));

    $photos = batchGetAllPhotos($allKeys);
    $photoBaseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/photo.php?url=';

    // Attach photos to subject
    if ($subject) {
        $rawPhotos = $photos[$subject['ListingKey']] ?? [];
        $rawPhotos = array_values(array_filter($rawPhotos, fn($u) => $u && strlen($u) > 5));
        $subject['_photos'] = array_map(fn($u) => $photoBaseUrl . urlencode($u), $rawPhotos);
    }

    // Attach photos + distance to comps
    foreach ($comps as &$comp) {
        $rawPhotos = $photos[$comp['ListingKey'] ?? ''] ?? [];
        $rawPhotos = array_values(array_filter($rawPhotos, fn($u) => $u && strlen($u) > 5));
        $comp['_photos'] = array_map(fn($u) => $photoBaseUrl . urlencode($u), $rawPhotos);

        // Distance from subject
        $cLat = (float)($comp['Latitude'] ?? 0);
        $cLng = (float)($comp['Longitude'] ?? 0);
        if ($cLat && $cLng) {
            $comp['_distance'] = haversineDistance($geo['lat'], $geo['lng'], $cLat, $cLng);
            $comp['_distanceFt'] = round($comp['_distance'] * 5280);
        }
    }
    unset($comp);

    // Sort comps by distance
    usort($comps, fn($a, $b) => ($a['_distance'] ?? 999) <=> ($b['_distance'] ?? 999));

    echo json_encode([
        'success'  => true,
        'geocoded' => $geo,
        'subject'  => $subject,
        'comps'    => $comps,
        'compCount'=> count($comps),
        'address'  => $fullAddress,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    jsonError($e->getMessage());
}

// ── Find the subject property by address ──
function findSubjectProperty(array $addrParts, array $geo, string $selectFields): ?array {
    $filters = [];

    if ($addrParts['number']) {
        $filters[] = "StreetNumber eq '" . addslashes($addrParts['number']) . "'";
    }
    if ($addrParts['street']) {
        // Use first word of street name for broader matching
        $streetWord = strtok($addrParts['street'], ' ');
        $filters[] = "contains(StreetName, '" . addslashes($streetWord) . "')";
    }
    if ($addrParts['city']) {
        $filters[] = "City eq '" . addslashes($addrParts['city']) . "'";
    } elseif (!empty($geo['city'])) {
        $filters[] = "City eq '" . addslashes($geo['city']) . "'";
    }

    if (empty($filters)) return null;

    try {
        $result = trestleGet('Property', [
            '$filter'  => implode(' and ', $filters),
            '$select'  => $selectFields,
            '$top'     => 10,
            '$orderby' => 'ModificationTimestamp desc',
        ]);

        $props = $result['value'] ?? [];
        if (empty($props)) return null;

        // Return the most recent listing for this address
        return $props[0];
    } catch (Exception $e) {
        return null;
    }
}

// ── Get comps within radius ──
function getComps(array $geo, float $radiusMiles, string $selectFields): array {
    $lat = (float)$geo['lat'];
    $lng = (float)$geo['lng'];
    if (!$lat || !$lng) return [];

    $pad      = $radiusMiles * 1.2;
    $latDelta = $pad / 69.0;
    $lngDelta = $pad / (69.0 * cos(deg2rad($lat)));

    $filters = [
        "Latitude ge "  . round($lat - $latDelta, 6),
        "Latitude le "  . round($lat + $latDelta, 6),
        "Longitude ge " . round($lng - $lngDelta, 6),
        "Longitude le " . round($lng + $lngDelta, 6),
        "(StandardStatus eq 'Active' or StandardStatus eq 'Pending' or StandardStatus eq 'ActiveUnderContract' or (StandardStatus eq 'Closed' and CloseDate ge " . date('Y-m-d', strtotime('-180 days')) . "))",
    ];

    try {
        $result = trestleGet('Property', [
            '$filter'  => implode(' and ', $filters),
            '$select'  => $selectFields,
            '$top'     => 50,
            '$orderby' => 'ModificationTimestamp desc',
        ]);

        $properties = $result['value'] ?? [];

        // Filter by true haversine radius
        return array_values(array_filter($properties, function($p) use ($lat, $lng, $radiusMiles) {
            $pLat = (float)($p['Latitude'] ?? 0);
            $pLng = (float)($p['Longitude'] ?? 0);
            if (!$pLat || !$pLng) return false;
            return haversineDistance($lat, $lng, $pLat, $pLng) <= $radiusMiles;
        }));
    } catch (Exception $e) {
        return [];
    }
}

function parseAddressString(string $addr): array {
    $parts = ['number'=>'','street'=>'','city'=>'','state'=>'','zip'=>''];
    if (preg_match('/^(\d+)\s+(.+?),\s*(.+?),?\s*([A-Z]{2})\s*(\d{5})?/i', $addr, $m)) {
        $parts['number'] = $m[1];
        $parts['street'] = trim($m[2]);
        $parts['city']   = trim($m[3]);
        $parts['state']  = strtoupper($m[4]);
        $parts['zip']    = $m[5] ?? '';
    }
    return $parts;
}
