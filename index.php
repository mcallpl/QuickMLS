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
                <label for="radiusSlider">Comp Radius:</label>
                <input type="range" id="radiusSlider" min="0.05" max="1.0" step="0.025" value="0.125">
                <span id="radiusLabel">1/8 mi</span>
            </div>
            <?php endif; ?>
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
        <p class="modal-desc">Send a shareable link for this property to your client via text message. The link will show property details with your contact info only.</p>
        <form id="sendForm" class="send-form">
            <label for="clientPhone">Client's Phone Number</label>
            <input type="tel" id="clientPhone" placeholder="(555) 123-4567" required>
            <div id="sendStatus" class="send-status hidden"></div>
            <button type="submit" class="send-submit-btn">&#128233; Send via Text</button>
        </form>
        <div id="sendResult" class="send-result hidden">
            <div class="send-result-icon">&#9989;</div>
            <p>Link sent successfully!</p>
            <div id="sendResultUrl" class="send-result-url"></div>
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
