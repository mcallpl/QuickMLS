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
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/api.php';
require_once __DIR__ . '/../lib/geocode.php';
require_once __DIR__ . '/../lib/photos.php';
require_once __DIR__ . '/search_helpers.php';

// Require login
if (!isLoggedIn()) {
    jsonError('Not authenticated');
}

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
    'PublicRemarks', 'PrivateRemarks', 'SyndicationRemarks',
    // Features
    'Appliances', 'InteriorFeatures', 'ExteriorFeatures',
    'ParkingFeatures', 'LaundryFeatures', 'FireplaceFeatures',
    'WaterSource', 'Sewer', 'Electric',
    'FoundationDetails', 'ArchitecturalStyle', 'BuildingAreaTotal',
    'CommonWalls', 'ConstructionMaterials', 'DirectionFaces',
    'PatioAndPorchFeatures', 'SecurityFeatures', 'View',
    'WindowFeatures', 'Fencing',
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
    // Showing Agent
    'ShowingContactName', 'ShowingContactPhone', 'ShowingContactType',
    'ShowingInstructions',
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

    // 4. Get comps within radius (default 1/8 mile, admin-adjustable)
    $radiusMiles = floatval($_POST['radius_miles'] ?? 0.10);
    if ($radiusMiles < 0.05 || $radiusMiles > 2.0) $radiusMiles = 0.10;
    $propertyType = $subject['PropertyType'] ?? null;
    $comps = getComps($geo, $radiusMiles, $selectFields, $propertyType);

    // If no subject was found, use the first comp as the reference type
    // and filter out any comps that don't match its PropertyType
    if (!$propertyType && !empty($comps)) {
        $propertyType = $comps[0]['PropertyType'] ?? null;
        if ($propertyType) {
            $comps = array_values(array_filter($comps, function($c) use ($propertyType) {
                return ($c['PropertyType'] ?? '') === $propertyType;
            }));
        }
    }

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
        'success'      => true,
        'geocoded'     => $geo,
        'subject'      => $subject,
        'comps'        => $comps,
        'compCount'    => count($comps),
        'address'      => $fullAddress,
        'radius_miles' => $radiusMiles,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    jsonError($e->getMessage());
}

