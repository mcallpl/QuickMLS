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
$clientPhone    = trim($_POST['client_phone'] ?? '');

if (!$address) {
    echo json_encode(['success' => false, 'error' => 'Address is required']);
    exit;
}
if (!$clientPhone) {
    echo json_encode(['success' => false, 'error' => 'Client phone number is required']);
    exit;
}

// Clean phone to digits only, add +1 if needed
$phoneDigits = preg_replace('/\D/', '', $clientPhone);
if (strlen($phoneDigits) === 10) $phoneDigits = '1' . $phoneDigits;
$phoneFull = '+' . $phoneDigits;

// Generate share token
$token = bin2hex(random_bytes(16));

// Save to database
$db   = getDb();
$stmt = $db->prepare("INSERT INTO shares (token, address, hero_listing_key, radius_miles, created_by, client_phone) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssdis', $token, $address, $heroListingKey, $radiusMiles, $_SESSION['user_id'], $phoneFull);
$stmt->execute();
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

// Send via Twilio SMS
$smsResult = null;
if (TWILIO_SID && TWILIO_TOKEN && TWILIO_PHONE) {
    $message = "Here's the property info you requested: $shortUrl\n— " . TEAM_NAME;
    $smsResult = sendTwilioSms($phoneFull, $message);
}

echo json_encode([
    'success'   => true,
    'token'     => $token,
    'share_url' => $shortUrl,
    'sms_sent'  => $smsResult ? true : false,
    'sms_error' => $smsResult === false ? 'SMS send failed' : null,
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

// ── Twilio SMS ──
function sendTwilioSms(string $to, string $body): ?bool {
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'From' => TWILIO_PHONE,
            'To'   => $to,
            'Body' => $body,
        ]),
        CURLOPT_USERPWD => TWILIO_SID . ':' . TWILIO_TOKEN,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($code >= 200 && $code < 300);
}
