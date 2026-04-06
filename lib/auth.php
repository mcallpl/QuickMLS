<?php

function getAccessToken(): string {
    if (file_exists(TOKEN_CACHE_FILE)) {
        $cached = json_decode(file_get_contents(TOKEN_CACHE_FILE), true);
        if ($cached && $cached['expires_at'] > (time() + 60)) {
            return $cached['access_token'];
        }
    }

    $ch = curl_init(TRESTLE_BASE_URL . '/trestle/oidc/connect/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => TRESTLE_CLIENT_ID,
            'client_secret' => TRESTLE_CLIENT_SECRET,
            'grant_type'    => 'client_credentials',
            'scope'         => 'api',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Trestle authentication failed (HTTP $httpCode): $response");
    }

    $data = json_decode($response, true);
    file_put_contents(TOKEN_CACHE_FILE, json_encode([
        'access_token' => $data['access_token'],
        'expires_at'   => time() + $data['expires_in'],
    ]));

    return $data['access_token'];
}
