<?php
require_once __DIR__ . '/cache.php';

/**
 * Escape a value for use inside an OData string literal.
 * OData escapes a single quote by doubling it ('') — NOT with a backslash.
 * Using addslashes() here breaks any input containing an apostrophe
 * (e.g. "O'Brien") and is an injection vector.
 */
function odataEscape(string $value): string {
    return str_replace("'", "''", $value);
}

function trestleGet(string $endpoint, array $params = []): array {
    // Check cache first
    $cached = PropertyDataCache::get('trestle', ['endpoint' => $endpoint, 'params' => $params]);
    if ($cached !== null) {
        return $cached;
    }

    $token = getAccessToken();
    $url   = TRESTLE_BASE_URL . '/trestle/odata/' . $endpoint;
    if (!empty($params)) {
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . rawurlencode((string)$v);
        }
        $url .= '?' . implode('&', $parts);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 429) throw new Exception("MLS quota exceeded — please wait a moment.");
    if ($httpCode === 401) {
        @unlink(TOKEN_CACHE_FILE);
        throw new Exception("Authorization expired. Please try again.");
    }
    if ($httpCode !== 200) {
        // Log the raw upstream body for debugging, but don't leak it to the client.
        error_log("Trestle API error (HTTP $httpCode) on $endpoint: " . substr((string)$response, 0, 500));
        throw new Exception("MLS service error (HTTP $httpCode). Please try again.");
    }

    $result = json_decode($response, true) ?: [];

    // Cache the result
    PropertyDataCache::set('trestle', ['endpoint' => $endpoint, 'params' => $params], $result);

    return $result;
}
