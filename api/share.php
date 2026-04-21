<?php
/**
 * QuickMLS — Share / Send To Client API
 * POST: address, radius_miles, client_phone
 * Creates a share token, optionally shortens URL (Rebrandly), sends SMS (Twilio)
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/session.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$address        = trim($_POST['address'] ?? '');
$heroListingKey = trim($_POST['hero_listing_key'] ?? '');
$radiusMiles    = floatval($_POST['radius_miles'] ?? 0.125);
$filterTypes    = trim($_POST['filter_types'] ?? '[]');
$filterSubTypes = trim($_POST['filter_subtypes'] ?? '[]');
$snapshotHero   = trim($_POST['snapshot_hero']  ?? '');
$snapshotComps  = trim($_POST['snapshot_comps'] ?? '[]');
$mapZoom        = intval($_POST['map_zoom'] ?? 0) ?: null;
$mapLat         = ($v = floatval($_POST['map_lat'] ?? 0)) ? $v : null;
$mapLng         = ($v = floatval($_POST['map_lng'] ?? 0)) ? $v : null;

// Validate JSON blobs
if (!is_array(json_decode($filterTypes, true)))    $filterTypes    = '[]';
if (!is_array(json_decode($filterSubTypes, true))) $filterSubTypes = '[]';
if (!is_array(json_decode($snapshotComps, true)))  $snapshotComps  = '[]';
if (!json_decode($snapshotHero))                   $snapshotHero   = null;

if (!$address) {
    echo json_encode(['success' => false, 'error' => 'Address is required']);
    exit;
}

// Generate share token
$token = bin2hex(random_bytes(16));

// Save to database
$db   = getDb();

$stmt = $db->prepare("INSERT INTO shares (token, address, hero_listing_key, radius_miles, filter_types, filter_subtypes, created_by, snapshot_hero, snapshot_comps, map_zoom, map_lat, map_lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->error]);
    exit;
}
$stmt->bind_param('sssdssissidd', $token, $address, $heroListingKey, $radiusMiles, $filterTypes, $filterSubTypes, $_SESSION['user_id'], $snapshotHero, $snapshotComps, $mapZoom, $mapLat, $mapLng);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to save share: ' . $stmt->error]);
    exit;
}
$stmt->close();

// Build the share URL
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$longUrl = "$scheme://$host$baseDir/view.php?t=$token";

// Try Rebrandly shortening
$shortUrl = $longUrl;
if (REBRANDLY_API_KEY) {
    $shortUrl = rebrandlyShorten($longUrl) ?: $longUrl;
}

echo json_encode([
    'success'   => true,
    'token'     => $token,
    'share_url' => $shortUrl,
]);

// ── Rebrandly URL shortener ──
function rebrandlyShorten(string $url): ?string {
    $ch = curl_init('https://api.rebrandly.com/v1/links');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'destination' => $url,
            'domain'      => ['fullName' => REBRANDLY_DOMAIN],
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . REBRANDLY_API_KEY,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $resp) {
        $data = json_decode($resp, true);
        return $data['shortUrl'] ? ('https://' . $data['shortUrl']) : null;
    }
    return null;
}
