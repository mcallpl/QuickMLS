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

// Database defaults
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'quickmls');

// Twilio defaults
if (!defined('TWILIO_SID'))   define('TWILIO_SID',   '');
if (!defined('TWILIO_TOKEN')) define('TWILIO_TOKEN', '');
if (!defined('TWILIO_PHONE')) define('TWILIO_PHONE', '');

// Rebrandly (optional — falls back to direct link)
if (!defined('REBRANDLY_API_KEY')) define('REBRANDLY_API_KEY', '');
if (!defined('REBRANDLY_DOMAIN'))  define('REBRANDLY_DOMAIN',  'rebrand.ly');

// ── Agent Profile (Chip) ──────────────────────────────────
define('AGENT_NAME',        'Chip McAllister');
define('AGENT_TITLE',       'Broker Associate');
define('AGENT_LICENSE',     '01971252');
define('AGENT_EMAIL',       'Chip@chipandkim.com');
define('AGENT_PHONE',       '(949) 735-9415');

// ── Agent Profile (Kim) ──────────────────────────────────
define('AGENT2_NAME',       'Kim McAllister');
define('AGENT2_TITLE',      'Realtor');
define('AGENT2_LICENSE',    '01967564');
define('AGENT2_EMAIL',      'Kim@chipandkim.com');
define('AGENT2_PHONE',      '(949) 735-4047');

// ── Team / Branding ──────────────────────────────────────
define('TEAM_NAME',         'Chip & Kim McAllister');
define('TEAM_OFFICE',       'HomeSmart, Evergreen Realty');
