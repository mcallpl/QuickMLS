<?php
// ============================================================
//  QuickMLS — Configuration
// ============================================================

// Load local secrets (not committed to git)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Trestle / Cotality
define('TRESTLE_BASE_URL',    'https://api.cotality.com');
if (!defined('TRESTLE_CLIENT_ID'))    define('TRESTLE_CLIENT_ID',    'YOUR_TRESTLE_CLIENT_ID');
if (!defined('TRESTLE_CLIENT_SECRET')) define('TRESTLE_CLIENT_SECRET', 'YOUR_TRESTLE_CLIENT_SECRET');
define('TOKEN_CACHE_FILE',    sys_get_temp_dir() . '/quickmls_token.json');

// Google Maps (Places Autocomplete + Street View)
if (!defined('GOOGLE_MAPS_API_KEY')) define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY');

// ── Agent Profile ──────────────────────────────────────────
define('AGENT_NAME',        'Chip McAllister');
define('AGENT_TITLE',       'Broker Associate');
define('AGENT_LICENSE',     '01971252');
define('AGENT_EMAIL',       'Chip@chipandkim.com');
define('AGENT_PHONE',       '(949) 735-9415');
