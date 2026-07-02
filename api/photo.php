<?php
/**
 * QuickMLS — Photo proxy
 * Proxies Trestle media URLs with auth token so images load in the browser.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';

$url = $_GET['url'] ?? '';
if (empty($url) || !str_starts_with($url, 'http')) {
    http_response_code(400);
    exit('Missing URL');
}

// ── SSRF guard ───────────────────────────────────────────────
// This endpoint is intentionally reachable without login (the public client
// view loads photos through it), and it attaches a live MLS bearer token to
// the outbound request. Restrict the target to trusted media hosts so it can't
// be abused as an open proxy / SSRF vector.
$allowedHostSuffixes = [
    'cotality.com',        // Trestle/Cotality media (api.cotality.com)
    'corelogic.com',       // legacy CoreLogic media hosts
    'googleapis.com',      // Google Street View (maps.googleapis.com) for off-market
];
$parsed = parse_url($url);
$host   = strtolower($parsed['host'] ?? '');
$schemeOk = in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'], true);
$hostOk = false;
foreach ($allowedHostSuffixes as $suffix) {
    if ($host === $suffix || str_ends_with($host, '.' . $suffix)) { $hostOk = true; break; }
}
if (!$schemeOk || !$hostOk) {
    http_response_code(403);
    exit('Host not allowed');
}

// Only MLS hosts should receive the Trestle bearer token. Google Street View
// (googleapis.com) is public and must NOT be handed a live MLS credential.
$mlsHostSuffixes = ['cotality.com', 'corelogic.com'];
$isMlsHost = false;
foreach ($mlsHostSuffixes as $suffix) {
    if ($host === $suffix || str_ends_with($host, '.' . $suffix)) { $isMlsHost = true; break; }
}

try {
    $token = getAccessToken();
} catch (Exception $e) {
    http_response_code(500);
    exit('Auth failed');
}

// Fetch with manual, tightly-controlled redirect handling.
// The first request goes to the allow-listed MLS host WITH the bearer token.
// MLS media routinely 302s to a signed CDN URL (e.g. media.crmls.org) that does
// NOT need our token — so on every redirect hop we DROP the Authorization header
// (prevents leaking the live MLS token to the redirect target) and require the
// redirect to be https (blocks http-only internal SSRF targets like 169.254.169.254).
// We never let curl auto-follow, because it would re-send the token to any host.
$fetchUrl  = $url;
$sendToken = $isMlsHost; // never send the MLS token to Google Street View
$body = null; $httpCode = 0; $mime = 'image/jpeg';

for ($hop = 0; $hop < 4; $hop++) {
    $ch = curl_init($fetchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER     => $sendToken ? ["Authorization: Bearer $token"] : [],
    ]);
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mime     = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL); // Location, since we don't auto-follow
    curl_close($ch);

    if ($httpCode >= 300 && $httpCode < 400 && $redirect) {
        if (stripos($redirect, 'https://') !== 0) {
            http_response_code(502);
            exit('Photo unavailable');
        }
        $fetchUrl  = $redirect;
        $sendToken = false; // never forward the MLS token past the trusted first host
        continue;
    }
    break;
}

if ($httpCode !== 200 || empty($body)) {
    http_response_code(502);
    exit('Photo unavailable');
}

header('Content-Type: ' . ($mime ?: 'image/jpeg'));
header('Cache-Control: public, max-age=86400');
echo $body;
