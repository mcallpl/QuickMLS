/* ============================================================
   QuickMLS — Main Application JS
   ============================================================ */

(function() {
    'use strict';

    // ── DOM refs ──
    const addressInput = document.getElementById('addressInput');
    const clearBtn     = document.getElementById('clearBtn');
    const searchBtn    = document.getElementById('searchBtn');
    const loader       = document.getElementById('loader');
    const results      = document.getElementById('results');
    const noResults    = document.getElementById('noResults');

    // Quick sheet elements
    const qsPhoto     = document.getElementById('qsPhoto');
    const qsPhotoNav  = document.getElementById('qsPhotoNav');
    const photoPrev   = document.getElementById('photoPrev');
    const photoNext   = document.getElementById('photoNext');
    const photoCounter = document.getElementById('photoCounter');
    const qsStatus    = document.getElementById('qsStatus');
    const qsAddress   = document.getElementById('qsAddress');
    const qsCityLine  = document.getElementById('qsCityLine');
    const qsPrice     = document.getElementById('qsPrice');
    const qsPriceSqft = document.getElementById('qsPricePerSqft');
    const qsBeds      = document.getElementById('qsBeds');
    const qsBaths     = document.getElementById('qsBaths');
    const qsSqft      = document.getElementById('qsSqft');
    const qsYear      = document.getElementById('qsYear');
    const qsLot       = document.getElementById('qsLot');
    const qsGarage    = document.getElementById('qsGarage');
    const qsExtras    = document.getElementById('qsExtras');

    // State
    let currentPhotos = [];
    let currentPhotoIdx = 0;
    let map = null;
    let markers = [];

    // ── Clear button ──
    addressInput.addEventListener('input', function() {
        clearBtn.classList.toggle('hidden', !this.value);
    });
    clearBtn.addEventListener('click', function() {
        addressInput.value = '';
        clearBtn.classList.add('hidden');
        addressInput.focus();
    });

    // ── Search triggers ──
    searchBtn.addEventListener('click', doSearch);
    addressInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            // Delay slightly to let Google Places autocomplete finish
            setTimeout(doSearch, 150);
        }
    });

    // ── Main search ──
    function doSearch() {
        const addr = addressInput.value.trim();
        if (!addr) return;

        showLoader();
        hideResults();

        const form = new FormData();
        form.append('full_address', addr);

        fetch('api/search.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
                hideLoader();
                if (!data.success) {
                    showNoResults(data.error || 'Search failed.');
                    return;
                }
                if (!data.subject && data.comps.length === 0) {
                    showNoResults('No MLS listings found for this address. Try a different address or check the spelling.');
                    return;
                }
                renderResults(data);
            })
            .catch(err => {
                hideLoader();
                showNoResults('Network error: ' + err.message);
            });
    }

    // ── Render all results ──
    function renderResults(data) {
        results.classList.remove('hidden');
        noResults.classList.add('hidden');

        const subj = data.subject;
        if (subj) {
            renderQuickSheet(subj);
            renderAgentInfo(subj);
            renderRemarks(subj);
        } else {
            // No subject found — show address from geocode
            document.getElementById('quickSheet').style.display = 'none';
            document.getElementById('agentSection').classList.add('hidden');
            document.getElementById('remarksSection').classList.add('hidden');
        }

        renderMap(data.geocoded, subj, data.comps);
        renderCompCards(data.comps);
        document.getElementById('compCount').textContent = '(' + data.comps.length + ' properties)';
    }

    // ── Quick Sheet ──
    function renderQuickSheet(p) {
        document.getElementById('quickSheet').style.display = '';

        // Photos
        currentPhotos = p._photos || [];
        currentPhotoIdx = 0;
        if (currentPhotos.length > 0) {
            qsPhoto.style.backgroundImage = 'url(' + currentPhotos[0] + ')';
            if (currentPhotos.length > 1) {
                qsPhotoNav.classList.remove('hidden');
                updatePhotoCounter();
            } else {
                qsPhotoNav.classList.add('hidden');
            }
        } else {
            qsPhoto.style.backgroundImage = 'none';
            qsPhoto.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-dim);font-size:48px;">&#127968;</div>';
            qsPhotoNav.classList.add('hidden');
        }

        // Status badge
        const status = p.StandardStatus || 'Unknown';
        qsStatus.textContent = formatStatus(status);
        qsStatus.className = 'qs-status-badge ' + statusClass(status);

        // Address
        const street = [p.StreetNumber, p.StreetDirPrefix, p.StreetName, p.StreetSuffix, p.StreetDirSuffix]
            .filter(Boolean).join(' ');
        const unit = p.UnitNumber ? ' #' + p.UnitNumber : '';
        qsAddress.textContent = street + unit;
        qsCityLine.textContent = [p.City, p.StateOrProvince, p.PostalCode].filter(Boolean).join(', ');

        // Price
        const price = p.ClosePrice || p.ListPrice;
        qsPrice.textContent = price ? '$' + Number(price).toLocaleString() : '—';
        if (price && p.LivingArea) {
            qsPriceSqft.textContent = '$' + Math.round(price / p.LivingArea).toLocaleString() + '/sq ft';
        } else {
            qsPriceSqft.textContent = '';
        }

        // Stats
        qsBeds.textContent  = p.BedroomsTotal ?? '—';
        qsBaths.textContent = p.BathroomsTotalInteger ?? '—';
        qsSqft.textContent  = p.LivingArea ? Number(p.LivingArea).toLocaleString() : '—';
        qsYear.textContent  = p.YearBuilt ?? '—';
        qsGarage.textContent = p.GarageSpaces ?? '—';

        // Lot
        if (p.LotSizeAcres && p.LotSizeAcres > 0) {
            qsLot.textContent = p.LotSizeAcres >= 1
                ? p.LotSizeAcres.toFixed(2) + ' ac'
                : Math.round(p.LotSizeAcres * 43560).toLocaleString() + ' sf';
        } else if (p.LotSizeSquareFeet) {
            qsLot.textContent = Number(p.LotSizeSquareFeet).toLocaleString() + ' sf';
        } else {
            qsLot.textContent = '—';
        }

        // Extras
        const extras = [];
        if (p.PropertyType) extras.push(p.PropertyType);
        if (p.PropertySubType) extras.push(p.PropertySubType);
        if (p.StoriesTotal) extras.push(p.StoriesTotal + ' stories');
        if (p.PoolPrivateYN === true || p.PoolPrivateYN === 'Yes') extras.push('Pool');
        if (p.AssociationFee) extras.push('HOA $' + p.AssociationFee + '/' + (p.AssociationFeeFrequency || 'mo'));
        if (p.DaysOnMarket != null) extras.push(p.DaysOnMarket + ' DOM');
        if (p.TaxAnnualAmount) extras.push('Tax $' + Number(p.TaxAnnualAmount).toLocaleString() + '/yr');
        if (p.ListingId) extras.push('MLS# ' + p.ListingId);

        qsExtras.innerHTML = extras.map(e => '<span class="qs-extra-tag">' + escHtml(e) + '</span>').join('');
    }

    // ── Photo navigation ──
    photoPrev.addEventListener('click', function() {
        if (currentPhotos.length < 2) return;
        currentPhotoIdx = (currentPhotoIdx - 1 + currentPhotos.length) % currentPhotos.length;
        qsPhoto.style.backgroundImage = 'url(' + currentPhotos[currentPhotoIdx] + ')';
        updatePhotoCounter();
    });
    photoNext.addEventListener('click', function() {
        if (currentPhotos.length < 2) return;
        currentPhotoIdx = (currentPhotoIdx + 1) % currentPhotos.length;
        qsPhoto.style.backgroundImage = 'url(' + currentPhotos[currentPhotoIdx] + ')';
        updatePhotoCounter();
    });
    function updatePhotoCounter() {
        photoCounter.textContent = (currentPhotoIdx + 1) + ' / ' + currentPhotos.length;
    }

    // ── Agent Info ──
    function renderAgentInfo(p) {
        const section = document.getElementById('agentSection');
        const container = document.getElementById('agentCards');
        container.innerHTML = '';
        let hasAgent = false;

        // Listing Agent
        if (p.ListAgentFullName) {
            hasAgent = true;
            container.innerHTML += agentCard('Listing Agent', p.ListAgentFullName, p.ListOfficeName, p.ListAgentDirectPhone, p.ListAgentEmail);
        }

        // Buyer Agent
        if (p.BuyerAgentFullName) {
            hasAgent = true;
            container.innerHTML += agentCard('Buyer Agent', p.BuyerAgentFullName, p.BuyerOfficeName, p.BuyerAgentDirectPhone, p.BuyerAgentEmail);
        }

        // Co-List Agent
        if (p.CoListAgentFullName) {
            hasAgent = true;
            container.innerHTML += agentCard('Co-List Agent', p.CoListAgentFullName, '', p.CoListAgentDirectPhone, p.CoListAgentEmail);
        }

        section.classList.toggle('hidden', !hasAgent);
    }

    function agentCard(role, name, office, phone, email) {
        let html = '<div class="agent-card">';
        html += '<div class="agent-card-role">' + escHtml(role) + '</div>';
        html += '<div class="agent-card-name">' + escHtml(name) + '</div>';
        if (office) html += '<div class="agent-card-office">' + escHtml(office) + '</div>';
        html += '<div class="agent-card-contact">';
        if (phone) {
            const cleanPhone = phone.replace(/\D/g, '');
            html += '<div class="agent-contact-row"><span class="icon">&#128222;</span><a href="tel:' + cleanPhone + '">' + escHtml(phone) + '</a></div>';
        }
        if (email) {
            html += '<div class="agent-contact-row"><span class="icon">&#9993;</span><a href="mailto:' + escHtml(email) + '">' + escHtml(email) + '</a></div>';
        }
        if (!phone && !email) {
            html += '<div class="agent-contact-row" style="color:var(--text-dim)">No contact info available</div>';
        }
        html += '</div></div>';
        return html;
    }

    // ── Remarks ──
    function renderRemarks(p) {
        const section = document.getElementById('remarksSection');
        const text = document.getElementById('remarksText');
        if (p.PublicRemarks) {
            text.textContent = p.PublicRemarks;
            section.classList.remove('hidden');
        } else {
            section.classList.add('hidden');
        }
    }

    // ── Map ──
    function renderMap(geo, subject, comps) {
        const mapEl = document.getElementById('map');

        if (map) {
            map.remove();
            map = null;
        }
        markers = [];

        map = L.map(mapEl).setView([geo.lat, geo.lng], 16);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
            maxZoom: 19,
        }).addTo(map);

        // 1/8 mile radius circle (≈ 201 meters)
        L.circle([geo.lat, geo.lng], {
            radius: 201,
            color: '#58a6ff',
            fillColor: '#58a6ff',
            fillOpacity: 0.08,
            weight: 1.5,
            dashArray: '6,4',
        }).addTo(map);

        // Subject marker (larger, different color)
        if (subject && subject.Latitude && subject.Longitude) {
            const subjectIcon = L.divIcon({
                html: '<div style="width:18px;height:18px;background:#ff6b6b;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.5);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9],
                className: '',
            });
            const m = L.marker([subject.Latitude, subject.Longitude], { icon: subjectIcon }).addTo(map);
            const price = subject.ClosePrice || subject.ListPrice;
            m.bindPopup(
                '<b>SUBJECT PROPERTY</b><br>' +
                '<div class="popup-price">$' + (price ? Number(price).toLocaleString() : '—') + '</div>' +
                '<div class="popup-addr">' + escHtml(formatAddr(subject)) + '</div>'
            );
        }

        // Comp markers
        comps.forEach(function(c) {
            if (!c.Latitude || !c.Longitude) return;
            // Skip if same as subject
            if (subject && c.ListingKey === subject.ListingKey) return;

            const color = statusMarkerColor(c.StandardStatus);
            const icon = L.divIcon({
                html: '<div style="width:12px;height:12px;background:' + color + ';border:2px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,0.4);"></div>',
                iconSize: [12, 12],
                iconAnchor: [6, 6],
                className: '',
            });

            const m = L.marker([c.Latitude, c.Longitude], { icon: icon }).addTo(map);
            const price = c.ClosePrice || c.ListPrice;
            let popup = '<div class="popup-price">$' + (price ? Number(price).toLocaleString() : '—') + '</div>';
            popup += '<div class="popup-addr">' + escHtml(formatAddr(c)) + '</div>';
            popup += '<div style="margin-top:4px;font-size:12px;">';
            popup += (c.BedroomsTotal || '—') + ' bd | ' + (c.BathroomsTotalInteger || '—') + ' ba | ' + (c.LivingArea ? Number(c.LivingArea).toLocaleString() + ' sqft' : '—');
            popup += '</div>';
            if (c._distanceFt) popup += '<div style="font-size:11px;color:#8b949e;margin-top:2px;">' + c._distanceFt.toLocaleString() + ' ft away</div>';
            if (c.ListAgentFullName) popup += '<div style="font-size:11px;color:#58a6ff;margin-top:2px;">' + escHtml(c.ListAgentFullName) + '</div>';
            m.bindPopup(popup);
            markers.push(m);
        });

        // Fit bounds
        setTimeout(function() { map.invalidateSize(); }, 100);
    }

    // ── Comp Cards ──
    function renderCompCards(comps) {
        const container = document.getElementById('compsList');
        container.innerHTML = '';

        comps.forEach(function(c) {
            const price = c.ClosePrice || c.ListPrice;
            const photo = (c._photos && c._photos[0]) || '';
            const status = c.StandardStatus || '';

            let html = '<div class="comp-card" data-lat="' + (c.Latitude||'') + '" data-lng="' + (c.Longitude||'') + '">';

            // Photo
            if (photo) {
                html += '<div class="comp-card-img" style="background-image:url(' + escAttr(photo) + ')"></div>';
            } else {
                html += '<div class="comp-card-img" style="display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--text-dim);">&#127968;</div>';
            }

            html += '<div class="comp-card-body">';
            html += '<div class="comp-card-status ' + statusClass(status) + '">' + formatStatus(status) + '</div>';
            html += '<div class="comp-card-price">$' + (price ? Number(price).toLocaleString() : '—') + '</div>';
            html += '<div class="comp-card-addr">' + escHtml(formatAddr(c)) + '</div>';

            html += '<div class="comp-card-stats">';
            html += '<div><span>' + (c.BedroomsTotal || '—') + '</span> bd</div>';
            html += '<div><span>' + (c.BathroomsTotalInteger || '—') + '</span> ba</div>';
            html += '<div><span>' + (c.LivingArea ? Number(c.LivingArea).toLocaleString() : '—') + '</span> sqft</div>';
            html += '</div>';

            if (c._distanceFt) {
                html += '<div class="comp-card-distance">' + c._distanceFt.toLocaleString() + ' ft away</div>';
            }
            if (c.ListAgentFullName) {
                html += '<div class="comp-card-agent">&#128100; ' + escHtml(c.ListAgentFullName);
                if (c.ListAgentDirectPhone) html += ' &middot; ' + escHtml(c.ListAgentDirectPhone);
                html += '</div>';
            }

            html += '</div></div>';
            container.innerHTML += html;
        });

        // Click comp card → zoom to marker on map
        container.querySelectorAll('.comp-card').forEach(function(card) {
            card.addEventListener('click', function() {
                const lat = parseFloat(this.dataset.lat);
                const lng = parseFloat(this.dataset.lng);
                if (lat && lng && map) {
                    map.setView([lat, lng], 18);
                    // Open the nearest marker's popup
                    markers.forEach(function(m) {
                        const pos = m.getLatLng();
                        if (Math.abs(pos.lat - lat) < 0.0001 && Math.abs(pos.lng - lng) < 0.0001) {
                            m.openPopup();
                        }
                    });
                }
            });
        });
    }

    // ── Helpers ──
    function showLoader() { loader.classList.remove('hidden'); }
    function hideLoader() { loader.classList.add('hidden'); }
    function hideResults() {
        results.classList.add('hidden');
        noResults.classList.add('hidden');
    }
    function showNoResults(msg) {
        document.getElementById('noResultsMsg').textContent = msg;
        noResults.classList.remove('hidden');
    }

    function formatAddr(p) {
        const street = [p.StreetNumber, p.StreetName].filter(Boolean).join(' ');
        const unit = p.UnitNumber ? ' #' + p.UnitNumber : '';
        return street + unit + ', ' + [p.City, p.StateOrProvince].filter(Boolean).join(', ');
    }

    function formatStatus(s) {
        const map = {
            'Active': 'Active',
            'Pending': 'Pending',
            'Closed': 'Closed',
            'ActiveUnderContract': 'Under Contract',
            'ComingSoon': 'Coming Soon',
            'Canceled': 'Canceled',
            'Expired': 'Expired',
        };
        return map[s] || s;
    }

    function statusClass(s) {
        if (s === 'Active' || s === 'ComingSoon') return 'active';
        if (s === 'Pending') return 'pending';
        if (s === 'Closed') return 'closed';
        if (s === 'ActiveUnderContract') return 'contract';
        return '';
    }

    function statusMarkerColor(s) {
        if (s === 'Active' || s === 'ComingSoon') return '#3fb950';
        if (s === 'Pending') return '#d29922';
        if (s === 'Closed') return '#f85149';
        if (s === 'ActiveUnderContract') return '#bc8cff';
        return '#8b949e';
    }

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function escAttr(s) {
        return (s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ── Google Places Autocomplete ──
    window.initPlaces = function() {
        var autocomplete = new google.maps.places.Autocomplete(addressInput, {
            types: ['address'],
            componentRestrictions: { country: 'us' },
            fields: ['formatted_address']
        });

        autocomplete.addListener('place_changed', function() {
            var place = autocomplete.getPlace();
            if (place && place.formatted_address) {
                addressInput.value = place.formatted_address;
                clearBtn.classList.remove('hidden');
                doSearch();
            }
        });
    };

})();
