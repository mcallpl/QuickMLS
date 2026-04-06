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

        <!-- ═══ HERO PROPERTY — Full Detail ═══ -->
        <div id="heroSection" class="hero-section">

            <!-- Photo Carousel -->
            <div class="hero-carousel-wrap">
                <div id="heroCarousel" class="hero-carousel"></div>
                <button id="carouselLeft" class="carousel-arrow carousel-left">&#8249;</button>
                <button id="carouselRight" class="carousel-arrow carousel-right">&#8250;</button>
                <div id="carouselCounter" class="carousel-counter">1 / 1</div>
                <div id="heroStatusBadge" class="hero-status-badge">Active</div>
            </div>

            <!-- Property Details -->
            <div class="hero-body">
                <div class="hero-address-row">
                    <div>
                        <h2 id="heroAddress" class="hero-address"></h2>
                        <div id="heroCityLine" class="hero-city-line"></div>
                    </div>
                    <div class="hero-price-block">
                        <div id="heroPrice" class="hero-price"></div>
                        <div id="heroPriceSqft" class="hero-price-sqft"></div>
                    </div>
                </div>

                <!-- Key Stats Bar -->
                <div class="hero-stats-bar">
                    <div class="hero-stat"><span id="heroBeds">—</span><small>Beds</small></div>
                    <div class="hero-stat"><span id="heroBaths">—</span><small>Baths</small></div>
                    <div class="hero-stat"><span id="heroSqft">—</span><small>Sq Ft</small></div>
                    <div class="hero-stat"><span id="heroYear">—</span><small>Year Built</small></div>
                    <div class="hero-stat"><span id="heroLot">—</span><small>Lot</small></div>
                    <div class="hero-stat"><span id="heroGarage">—</span><small>Garage</small></div>
                    <div class="hero-stat"><span id="heroStories">—</span><small>Stories</small></div>
                    <div class="hero-stat"><span id="heroDom">—</span><small>DOM</small></div>
                </div>

                <!-- Tags Row -->
                <div id="heroTags" class="hero-tags"></div>

                <!-- Agent Info — RIGHT in the hero -->
                <div id="heroAgents" class="hero-agents"></div>

                <!-- Property Details Grid -->
                <div id="heroDetailsGrid" class="hero-details-grid"></div>

                <!-- Public Remarks -->
                <div id="heroPublicRemarks" class="hero-remarks hidden">
                    <h4>Public Remarks</h4>
                    <p id="heroPublicRemarksText"></p>
                </div>

                <!-- Private Remarks -->
                <div id="heroPrivateRemarks" class="hero-remarks hero-private-remarks hidden">
                    <h4>Private / Agent Remarks</h4>
                    <p id="heroPrivateRemarksText"></p>
                </div>

                <!-- Listing Meta -->
                <div id="heroMeta" class="hero-meta"></div>
            </div>
        </div>

        <!-- ═══ MAP + COMPS ═══ -->
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
