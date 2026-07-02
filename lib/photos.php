<?php

function batchGetAllPhotos(array $listingKeys): array {
    if (empty($listingKeys)) return [];

    $photos = [];
    $chunks = array_chunk($listingKeys, 2);

    foreach ($chunks as $chunk) {
        $orParts = array_map(fn($k) => "ResourceRecordKey eq '" . odataEscape((string)$k) . "'", $chunk);
        $filter  = '(' . implode(' or ', $orParts) . ')';

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $result = trestleGet('Media', [
                    '$filter'  => $filter,
                    '$select'  => 'ResourceRecordKey,MediaURL,Order',
                    '$orderby' => 'ResourceRecordKey asc,Order asc',
                    // 2 listings per request; raise the cap so a listing with 100+
                    // photos paired with another isn't truncated by a shared limit.
                    '$top'     => 500,
                ]);
                foreach ($result['value'] ?? [] as $m) {
                    $key = $m['ResourceRecordKey'];
                    $url = $m['MediaURL'] ?? '';
                    if (!$key || !$url) continue;
                    if (!isset($photos[$key])) $photos[$key] = [];
                    $photos[$key][] = $url;
                }
                break;
            } catch (Exception $e) {
                if ($attempt === 0) usleep(500000);
            }
        }
    }

    return $photos;
}

function getPhotosForListing(string $listingKey, int $limit = 100): array {
    try {
        $result = trestleGet('Media', [
            '$filter'  => "ResourceRecordKey eq '" . odataEscape($listingKey) . "'",
            '$orderby' => 'Order',
            '$select'  => 'MediaURL,Order,ShortDescription',
            '$top'     => $limit,
        ]);
        return $result['value'] ?? [];
    } catch (Exception $e) {
        return [];
    }
}
