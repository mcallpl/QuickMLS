<?php
/**
 * QuickMLS — Search Helper Functions
 * Shared between api/search.php and view.php
 */

function findSubjectProperty(array $addrParts, array $geo, string $selectFields): ?array {
    $filters = [];

    if ($addrParts['number']) {
        $filters[] = "StreetNumber eq '" . odataEscape($addrParts['number']) . "'";
    }
    if ($addrParts['street']) {
        // Use all significant words from the street name for a tighter match
        $streetWords = preg_split('/\s+/', $addrParts['street']);
        foreach ($streetWords as $word) {
            $word = trim($word);
            // Skip common suffixes — they may not be in StreetName (they're in StreetSuffix)
            if (in_array(strtolower($word), ['st','ave','blvd','dr','rd','ln','ct','cir','pl','way','pkwy','ter','trl'])) continue;
            if (strlen($word) >= 2) {
                $filters[] = "contains(StreetName, '" . odataEscape($word) . "')";
            }
        }
    }
    if ($addrParts['city']) {
        $filters[] = "City eq '" . odataEscape($addrParts['city']) . "'";
    } elseif (!empty($geo['city'])) {
        $filters[] = "City eq '" . odataEscape($geo['city']) . "'";
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

        $geolat = (float)($geo['lat'] ?? 0);
        $geolng = (float)($geo['lng'] ?? 0);
        // Geocoders (Nominatim) often land on the parcel edge or street centroid,
        // 100+ ft from the MLS-recorded coordinate. When we already have an exact
        // street-number + street-name match, that match identifies the property, so
        // allow a generous distance. The closest-only fallback (no exact match) uses
        // a tighter gate to avoid grabbing a neighbor.
        $exactMatchMaxDist = 0.06; // ~315 ft — tolerate geocoder disagreement on a confirmed match
        $fallbackMaxDist   = 0.02; // ~105 ft — no name match, so require real proximity

        // Try to find exact street number match first
        if ($addrParts['number']) {
            foreach ($props as $p) {
                if (($p['StreetNumber'] ?? '') == $addrParts['number']) {
                    // Verify street name contains all significant words
                    $sn = strtolower($p['StreetName'] ?? '');
                    $streetWords = preg_split('/\s+/', strtolower($addrParts['street']));
                    $match = true;
                    foreach ($streetWords as $w) {
                        if (in_array($w, ['st','ave','blvd','dr','rd','ln','ct','cir','pl','way','pkwy','ter','trl'])) continue;
                        if (strlen($w) >= 2 && strpos($sn, $w) === false) { $match = false; break; }
                    }
                    if ($match) {
                        // Coordinates missing entirely? Trust the number+name match.
                        $pLat = (float)($p['Latitude'] ?? 0);
                        $pLng = (float)($p['Longitude'] ?? 0);
                        if (!$pLat || !$pLng || !$geolat || !$geolng) {
                            return $p;
                        }
                        $dist = haversineDistance($geolat, $geolng, $pLat, $pLng);
                        if ($dist <= $exactMatchMaxDist) {
                            return $p;
                        }
                    }
                }
            }
        }

        // Fallback: find closest property within the tighter max distance
        $closest = null;
        $closestDist = $fallbackMaxDist;
        foreach ($props as $p) {
            $pLat = (float)($p['Latitude'] ?? 0);
            $pLng = (float)($p['Longitude'] ?? 0);
            if ($pLat && $pLng && $geolat && $geolng) {
                $dist = haversineDistance($geolat, $geolng, $pLat, $pLng);
                if ($dist <= $closestDist) {
                    $closest = $p;
                    $closestDist = $dist;
                }
            }
        }

        return $closest;
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
        $filters[] = "PropertyType eq '" . odataEscape($propertyType) . "'";
    }

    try {
        // Fetch up to 200 (OData can't order by computed distance, so we pull a
        // large slice of the bounding box and distance-filter/sort client-side).
        // The old $top=50 ordered by ModificationTimestamp dropped the *closest*
        // comps in dense blocks in favor of the most-recently-touched ones.
        $result = trestleGet('Property', [
            '$filter'  => implode(' and ', $filters),
            '$select'  => $selectFields,
            '$top'     => 200,
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

    // Comma-aware parse: pull state/zip from the last segment and the city from
    // the segment before it, so an intermediate unit line
    // ("123 Main St, Unit 5, Los Angeles, CA 90001") doesn't get mis-read as the
    // city ("Unit 5") / state ("LO").
    $segs = array_values(array_filter(array_map('trim', explode(',', $addr)), 'strlen'));
    // Google autocomplete appends a country segment ("..., USA"); drop it so the
    // last segment is the "ST ZIP" part, not "USA".
    if ($segs && preg_match('/^(USA|United States|US)$/i', end($segs))) {
        array_pop($segs);
    }
    if (count($segs) >= 3) {
        $last = array_pop($segs);
        if (preg_match('/^([A-Za-z]{2})\b\s*(\d{5})?/', $last, $m)) {
            $parts['state'] = strtoupper($m[1]);
            $parts['zip']   = $m[2] ?? '';
            $parts['city']  = array_pop($segs) ?? '';
        } else {
            // Last segment wasn't "ST ZIP"; treat it as the city.
            $parts['city'] = $last;
        }
        // Remaining segments are street + any unit line. Drop unit designators so
        // they don't leak into the StreetName filter.
        $streetSegs = [];
        foreach ($segs as $s) {
            if (preg_match('/^\s*(unit|apt|apartment|ste|suite|no\.?|#)\b/i', $s)) continue;
            $streetSegs[] = $s;
        }
        $streetFull = trim(implode(' ', $streetSegs));
        if (preg_match('/^(\d+)\s+(.+)$/', $streetFull, $m2)) {
            $parts['number'] = $m2[1];
            $parts['street'] = trim($m2[2]);
        } else {
            $parts['street'] = $streetFull;
        }
    } elseif (preg_match('/^(\d+)\s+(.+?),\s*(.+?),\s*([A-Z]{2})\s*(\d{5})?/i', $addr, $m)) {
        // "123 Street Name, City, State ZIP" with no unit line
        $parts['number'] = $m[1];
        $parts['street'] = trim($m[2]);
        $parts['city']   = trim($m[3]);
        $parts['state']  = strtoupper($m[4]);
        $parts['zip']    = $m[5] ?? '';
    } elseif (preg_match('/^(\d+)\s+(.+)$/', $addr, $m)) {
        // Bare "123 Street Name"
        $parts['number'] = $m[1];
        $parts['street'] = trim($m[2]);
    }

    // Strip a trailing inline unit ("Main St #5" -> "Main St").
    $parts['street'] = trim(preg_replace('/\s*#\s*\S+\s*$/', '', $parts['street']));

    return $parts;
}
