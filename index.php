<?php
require_once __DIR__ . '/config.php';
$v = time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickMLS — Instant Property Intelligence</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="css/style.css?v=<?=$v?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<!-- ── LOADING OVERLAY ──────────────────────────────────── -->
<div id="loader" class="loader hidden">
    <div class="loader-spinner"></div>
    <div class="loader-text">Pulling MLS data...</div>
</div>

<!-- ── APP ──────────────────────────────────────────────── -->
<div class="app">

    <!-- HEADER -->
    <header class="header">
        <div class="header-brand">
            <span class="header-icon">&#9889;</span>
            <h1>QuickMLS</h1>
        </div>
        <p class="header-tagline">Type an address. Get the full picture.</p>
    </header>

    <!-- SEARCH -->
    <div class="search-bar">
        <div class="search-inner">
            <span class="search-icon">&#128269;</span>
            <input
                type="text"
                id="addressInput"
                class="search-input"
                placeholder="Start typing an address..."
                autocomplete="off"
                spellcheck="false"
            >
            <button type="button" id="clearBtn" class="search-clear hidden">&times;</button>
            <button type="button" id="searchBtn" class="search-go">Go</button>
        </div>
    </div>

    <!-- RESULTS CONTAINER -->
    <div id="results" class="results hidden">

        <!-- QUICK SHEET -->
        <div id="quickSheet" class="quick-sheet">
            <div class="qs-photo-wrap">
                <div id="qsPhoto" class="qs-photo"></div>
                <div id="qsPhotoNav" class="qs-photo-nav hidden">
                    <button id="photoPrev" class="photo-nav-btn">&lsaquo;</button>
                    <span id="photoCounter" class="photo-counter">1 / 1</span>
                    <button id="photoNext" class="photo-nav-btn">&rsaquo;</button>
                </div>
            </div>

            <div class="qs-details">
                <div id="qsStatus" class="qs-status-badge">Active</div>
                <h2 id="qsAddress" class="qs-address"></h2>
                <div id="qsCityLine" class="qs-city-line"></div>

                <div class="qs-price-row">
                    <div id="qsPrice" class="qs-price"></div>
                    <div id="qsPricePerSqft" class="qs-price-sqft"></div>
                </div>

                <div class="qs-stats">
                    <div class="qs-stat"><span id="qsBeds" class="qs-stat-val">—</span><span class="qs-stat-label">Beds</span></div>
                    <div class="qs-stat"><span id="qsBaths" class="qs-stat-val">—</span><span class="qs-stat-label">Baths</span></div>
                    <div class="qs-stat"><span id="qsSqft" class="qs-stat-val">—</span><span class="qs-stat-label">Sq Ft</span></div>
                    <div class="qs-stat"><span id="qsYear" class="qs-stat-val">—</span><span class="qs-stat-label">Year Built</span></div>
                    <div class="qs-stat"><span id="qsLot" class="qs-stat-val">—</span><span class="qs-stat-label">Lot</span></div>
                    <div class="qs-stat"><span id="qsGarage" class="qs-stat-val">—</span><span class="qs-stat-label">Garage</span></div>
                </div>

                <div id="qsExtras" class="qs-extras"></div>
            </div>
        </div>

        <!-- AGENT INFO -->
        <div id="agentSection" class="agent-section hidden">
            <h3 class="section-title">Agent & Office Info</h3>
            <div id="agentCards" class="agent-cards"></div>
        </div>

        <!-- REMARKS -->
        <div id="remarksSection" class="remarks-section hidden">
            <h3 class="section-title">Description</h3>
            <p id="remarksText" class="remarks-text"></p>
        </div>

        <!-- MAP + COMPS -->
        <div class="map-comps-section">
            <h3 class="section-title">
                Comps Within &#8539; Mile
                <span id="compCount" class="comp-count"></span>
            </h3>
            <div id="map" class="map-container"></div>
            <div id="compsList" class="comps-list"></div>
        </div>

    </div><!-- /results -->

    <!-- NO RESULTS -->
    <div id="noResults" class="no-results hidden">
        <div class="no-results-icon">&#128270;</div>
        <p id="noResultsMsg">No MLS listing found for this address.</p>
    </div>

</div><!-- /app -->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="js/app.js?v=<?=$v?>"></script>
<script>
var GOOGLE_MAPS_KEY = <?= json_encode(GOOGLE_MAPS_API_KEY) ?>;
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=places&callback=initPlaces"
    async defer>
</script>

</body>
</html>
