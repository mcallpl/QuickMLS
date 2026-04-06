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

try {
    $token = getAccessToken();
} catch (Exception $e) {
    http_response_code(500);
    exit('Auth failed');
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
]);

$body     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$mime     = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode !== 200 || empty($body)) {
    http_response_code(502);
    exit('Photo unavailable');
}

header('Content-Type: ' . ($mime ?: 'image/jpeg'));
header('Cache-Control: public, max-age=86400');
echo $body;
