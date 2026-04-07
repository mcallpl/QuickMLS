<?php
/**
 * QuickMLS — Search Helper Functions
 * Shared between api/search.php and view.php
 */

function findSubjectProperty(array $addrParts, array $geo, string $selectFields): ?array {
    $filters = [];

    if ($addrParts['number']) {
        $filters[] = "StreetNumber eq '" . addslashes($addrParts['number']) . "'";
    }
    if ($addrParts['street']) {
        // Use all significant words from the street name for a tighter match
        $streetWords = preg_split('/\s+/', $addrParts['street']);
        foreach ($streetWords as $word) {
            $word = trim($word);
            // Skip common suffixes — they may not be in StreetName (they're in StreetSuffix)
            if (in_array(strtolower($word), ['st','ave','blvd','dr','rd','ln','ct','cir','pl','way','pkwy','ter','trl'])) continue;
            if (strlen($word) >= 2) {
                $filters[] = "contains(StreetName, '" . addslashes($word) . "')";
            }
        }
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

        // Prefer exact street number + street name match
        if ($addrParts['number']) {
            foreach ($props as $p) {
                if (($p['StreetNumber'] ?? '') === $addrParts['number']) {
                    // Also verify street name contains all words
                    $sn = strtolower($p['StreetName'] ?? '');
                    $streetWords = preg_split('/\s+/', strtolower($addrParts['street']));
                    $match = true;
                    foreach ($streetWords as $w) {
                        if (in_array($w, ['st','ave','blvd','dr','rd','ln','ct','cir','pl','way','pkwy','ter','trl'])) continue;
                        if (strlen($w) >= 2 && strpos($sn, $w) === false) { $match = false; break; }
                    }
                    if ($match) return $p;
                }
            }
        }

        return $props[0];
    } catch (Exception $e) {
        return null;
    }
}

function getComps(array $geo, float $radiusMiles, string $selectFields, ?string $propertyType = null): array {
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

    if ($propertyType) {
        $filters[] = "PropertyType eq '" . addslashes($propertyType) . "'";
    }

    try {
        $result = trestleGet('Property', [
            '$filter'  => implode(' and ', $filters),
            '$select'  => $selectFields,
            '$top'     => 50,
            '$orderby' => 'ModificationTimestamp desc',
        ]);

        $properties = $result['value'] ?? [];

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
    if (preg_match('/^(\d+)\s+(.+?),\s*(.+?),\s*([A-Z]{2})\s*(\d{5})?/i', $addr, $m)) {
        $parts['number'] = $m[1];
        $parts['street'] = trim($m[2]);
        $parts['city']   = trim($m[3]);
        $parts['state']  = strtoupper($m[4]);
        $parts['zip']    = $m[5] ?? '';
    }
    return $parts;
}
