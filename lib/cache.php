<?php
/**
 * PropertyData Cache Layer
 * Reduces API calls to Trestle and ATTOM by caching results
 * TTL: 1 hour (3600 seconds)
 */

class PropertyDataCache {
    private static $cacheDir = __DIR__ . '/../.cache';
    private static $ttl = 3600; // 1 hour

    /**
     * Initialize cache directory
     */
    public static function init() {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * Generate cache key from parameters
     */
    private static function getCacheKey(string $type, array $params): string {
        $key = $type . '_' . md5(json_encode($params));
        return $key;
    }

    /**
     * Get cached data if available and fresh
     */
    public static function get(string $type, array $params) {
        self::init();
        $key = self::getCacheKey($type, $params);
        $cacheFile = self::$cacheDir . '/' . $key . '.json';

        if (file_exists($cacheFile)) {
            $age = time() - filemtime($cacheFile);
            if ($age < self::$ttl) {
                $cached = json_decode(file_get_contents($cacheFile), true);
                if ($cached) {
                    error_log("[Cache HIT] $type - age: {$age}s");
                    return $cached;
                }
            } else {
                // Expired, remove it
                @unlink($cacheFile);
            }
        }

        return null;
    }

    /**
     * Store data in cache
     */
    public static function set(string $type, array $params, $data) {
        self::init();
        $key = self::getCacheKey($type, $params);
        $cacheFile = self::$cacheDir . '/' . $key . '.json';

        file_put_contents($cacheFile, json_encode($data));
        error_log("[Cache WRITE] $type");

        return $data;
    }

    /**
     * Clear all cache (useful for testing)
     */
    public static function clear() {
        self::init();
        $files = glob(self::$cacheDir . '/*.json');
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Get cache statistics
     */
    public static function stats(): array {
        self::init();
        $files = glob(self::$cacheDir . '/*.json');
        $totalSize = 0;
        $hitCount = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
            $hitCount++;
        }

        return [
            'cached_items' => count($files),
            'total_size_kb' => round($totalSize / 1024, 2),
            'ttl_seconds' => self::$ttl,
            'cache_dir' => self::$cacheDir
        ];
    }
}

// Initialize on include
PropertyDataCache::init();
