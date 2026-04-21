<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/session.php';

$loggedIn = isLoggedIn();
$isAdmin  = isAdmin();
$user     = currentUser();
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

<?php if (!$loggedIn): ?>
<!-- ── LOGIN SCREEN ────────────────────────────────────── -->
<div class="login-screen">
    <div class="login-card">
        <div class="header-brand">
            <span class="header-icon">&#9889;</span>
            <h1>QuickMLS</h1>
        </div>
        <p class="login-subtitle">Sign in to continue</p>
        <form id="loginForm" class="login-form">
            <input type="text" id="loginUser" placeholder="Username" autocomplete="username" required>
            <input type="password" id="loginPass" placeholder="Password" autocomplete="current-password" required>
            <button type="submit" class="login-btn">Sign In</button>
            <div id="loginError" class="login-error hidden"></div>
        </form>
    </div>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData();
    fd.append('username', document.getElementById('loginUser').value);
    fd.append('password', document.getElementById('loginPass').value);
    fetch('api/login.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) { location.reload(); }
            else {
                var el = document.getElementById('loginError');
                el.textContent = d.error;
                el.classList.remove('hidden');
            }
        })
        .catch(function() {
            var el = document.getElementById('loginError');
            el.textContent = 'Connection error';
            el.classList.remove('hidden');
        });
});
</script>

<?php else: ?>
<!-- ── LOADING OVERLAY ──────────────────────────────────── -->
<div id="loader" class="loader hidden">
    <div class="loader-spinner"></div>
    <div class="loader-text">Pulling MLS data...</div>
</div>

<!-- ── APP ──────────────────────────────────────────────── -->
<div class="app">

    <!-- HEADER -->
    <header class="header">
        <div class="header-top-row">
            <div class="header-brand">
                <span class="header-icon">&#9889;</span>
                <h1>QuickMLS</h1>
            </div>
            <div class="header-controls">
                <!-- Theme Toggle -->
                <label class="theme-toggle" title="Toggle light/dark mode">
                    <input type="checkbox" id="themeToggle">
                    <span class="toggle-slider">
                        <span class="toggle-icon sun">&#9788;</span>
                        <span class="toggle-icon moon">&#9790;</span>
                    </span>
                </label>
                <!-- User / Logout -->
                <span class="header-user"><?= htmlspecialchars($user['username']) ?></span>
                <a href="#" id="logoutBtn" class="header-logout" title="Sign out">&#9211;</a>
            </div>
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

        <?php if ($isAdmin): ?>
        <!-- Send To Client Button -->
        <div class="send-client-bar">
            <button type="button" id="sendClientBtn" class="send-client-btn">&#128233; Send To Client</button>
        </div>
        <?php endif; ?>

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
                Comps Within <span id="radiusDisplay">&#8539; Mile</span>
                <span id="compCount" class="comp-count"></span>
            </h3>
            <div id="map" class="map-container"></div>
            <?php if ($isAdmin): ?>
            <div class="radius-control">
                <div class="radius-header">
                    <label>Comp Radius: <span id="radiusLabel">0.10 mi</span></label>
                </div>
                <div class="radius-slider-wrap">
                    <input type="range" id="radiusSlider" min="0.05" max="1.0" step="0.05" value="0.10">
                    <div class="radius-ticks">
                        <span data-val="0.05">.05</span>
                        <span data-val="0.10">.10</span>
                        <span data-val="0.15">.15</span>
                        <span data-val="0.20">.20</span>
                        <span data-val="0.25">.25</span>
                        <span data-val="0.30">.30</span>
                        <span data-val="0.35">.35</span>
                        <span data-val="0.40">.40</span>
                        <span data-val="0.45">.45</span>
                        <span data-val="0.50">.50</span>
                        <span data-val="0.55">.55</span>
                        <span data-val="0.60">.60</span>
                        <span data-val="0.65">.65</span>
                        <span data-val="0.70">.70</span>
                        <span data-val="0.75">.75</span>
                        <span data-val="0.80">.80</span>
                        <span data-val="0.85">.85</span>
                        <span data-val="0.90">.90</span>
                        <span data-val="0.95">.95</span>
                        <span data-val="1.00">1.0</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <!-- Type Filters -->
            <div id="compFilters" class="comp-filters hidden"></div>
            <div id="compsList" class="comps-list"></div>
        </div>

    </div><!-- /results -->

    <!-- NO RESULTS -->
    <div id="noResults" class="no-results hidden">
        <div class="no-results-icon">&#128270;</div>
        <p id="noResultsMsg">No MLS listing found for this address.</p>
    </div>

</div><!-- /app -->

<!-- Send To Client Modal -->
<div id="sendModal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Send To Client</h3>
            <button type="button" id="sendModalClose" class="modal-close">&times;</button>
        </div>
        <p class="modal-desc">Generates a link and pre-writes the message — just copy and text it.</p>
        <div id="sendForm">
            <input type="text" id="clientFirstName" class="send-name-input" placeholder="Client's first name (optional)" autocomplete="off">
            <div id="sendStatus" class="send-status hidden"></div>
            <button type="button" id="generateLinkBtn" class="send-submit-btn">Generate Link &amp; Message</button>
        </div>
        <div id="sendResult" class="send-result hidden">
            <div class="send-result-icon">&#9989;</div>
            <p>Message ready — copy and text it!</p>
            <textarea id="sendMessageText" class="send-message-textarea" rows="7" spellcheck="true"></textarea>
            <div class="send-actions">
                <button type="button" id="copyLinkBtn" class="send-action-btn">&#128203; Copy Message</button>
                <button type="button" id="shareLinkBtn" class="send-action-btn send-action-share">&#128228; Share</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var APP_USER = <?= json_encode($user) ?>;
var IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
var CLIENT_MODE = false;
var GOOGLE_MAPS_KEY = <?= json_encode(GOOGLE_MAPS_API_KEY) ?>;
</script>
<script src="js/app.js?v=<?=$v?>"></script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&libraries=places&callback=initPlaces"
    async defer>
</script>

<?php endif; ?>

</body>
</html>
