<?php

function geocodeAddress(string $fullAddress): ?array {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q'              => $fullAddress,
        'format'         => 'json',
        'limit'          => 1,
        'addressdetails' => 1,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'QuickMLS/1.0 (chip@chipandkim.com)',
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (empty($data[0]['lat'])) return null;

    $addr  = $data[0]['address'] ?? [];
    $state = stateNameToAbbr($addr['state'] ?? '') ?: ($addr['state'] ?? '');
    $city  = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? '';

    return [
        'lat'          => (float)$data[0]['lat'],
        'lng'          => (float)$data[0]['lon'],
        'display_name' => $data[0]['display_name'] ?? $fullAddress,
        'postcode'     => $addr['postcode']         ?? '',
        'city'         => $city,
        'state'        => $state,
        'county'       => $addr['county']           ?? '',
    ];
}

function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 3959.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a    = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function stateNameToAbbr(string $name): string {
    $map = [
        'Alabama'=>'AL','Alaska'=>'AK','Arizona'=>'AZ','Arkansas'=>'AR',
        'California'=>'CA','Colorado'=>'CO','Connecticut'=>'CT','Delaware'=>'DE',
        'Florida'=>'FL','Georgia'=>'GA','Hawaii'=>'HI','Idaho'=>'ID',
        'Illinois'=>'IL','Indiana'=>'IN','Iowa'=>'IA','Kansas'=>'KS',
        'Kentucky'=>'KY','Louisiana'=>'LA','Maine'=>'ME','Maryland'=>'MD',
        'Massachusetts'=>'MA','Michigan'=>'MI','Minnesota'=>'MN','Mississippi'=>'MS',
        'Missouri'=>'MO','Montana'=>'MT','Nebraska'=>'NE','Nevada'=>'NV',
        'New Hampshire'=>'NH','New Jersey'=>'NJ','New Mexico'=>'NM','New York'=>'NY',
        'North Carolina'=>'NC','North Dakota'=>'ND','Ohio'=>'OH','Oklahoma'=>'OK',
        'Oregon'=>'OR','Pennsylvania'=>'PA','Rhode Island'=>'RI','South Carolina'=>'SC',
        'South Dakota'=>'SD','Tennessee'=>'TN','Texas'=>'TX','Utah'=>'UT',
        'Vermont'=>'VT','Virginia'=>'VA','Washington'=>'WA','West Virginia'=>'WV',
        'Wisconsin'=>'WI','Wyoming'=>'WY','District of Columbia'=>'DC',
    ];
    return $map[$name] ?? '';
}
