<?php

function trestleGet(string $endpoint, array $params = []): array {
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
    if ($httpCode !== 200) throw new Exception("MLS API error (HTTP $httpCode): $response");

    return json_decode($response, true) ?: [];
}
