<?php
/**
 * Cache Statistics & Management API
 * Monitor and manage property data cache
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/cache.php';

header('Content-Type: application/json');

// Admin-only: cache stats can be viewed and the cache cleared from here.
requireAdmin();

$action = $_GET['action'] ?? 'stats';

switch ($action) {
    case 'stats':
        echo json_encode([
            'status' => 'success',
            'cache' => PropertyDataCache::stats(),
            'estimated_monthly_savings' => [
                'description' => 'Based on 70-80% reduction in API calls',
                'trestle_monthly_before' => '$500-1000',
                'trestle_monthly_after' => '$100-300 (estimated)',
                'savings' => '$200-700/month from caching'
            ]
        ]);
        break;

    case 'clear':
        // Destructive: require POST so it can't be triggered via a GET link/img (CSRF).
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Use POST to clear the cache']);
            break;
        }
        PropertyDataCache::clear();
        echo json_encode([
            'status' => 'success',
            'message' => 'Cache cleared'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
