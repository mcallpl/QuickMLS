<?php

function batchGetAllPhotos(array $listingKeys): array {
    if (empty($listingKeys)) return [];

    $photos = [];
    $chunks = array_chunk($listingKeys, 2);

    foreach ($chunks as $chunk) {
        $orParts = array_map(fn($k) => "ResourceRecordKey eq '$k'", $chunk);
        $filter  = '(' . implode(' or ', $orParts) . ')';

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $result = trestleGet('Media', [
                    '$filter'  => $filter,
                    '$select'  => 'ResourceRecordKey,MediaURL,Order',
                    '$orderby' => 'ResourceRecordKey asc,Order asc',
                    '$top'     => 200,
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
            '$filter'  => "ResourceRecordKey eq '$listingKey'",
            '$orderby' => 'Order',
            '$select'  => 'MediaURL,Order,ShortDescription',
            '$top'     => $limit,
        ]);
        return $result['value'] ?? [];
    } catch (Exception $e) {
        return [];
    }
}
