/* ============================================================
   QuickMLS — Main Application JS
   Hero property with full detail + comp swap
   ============================================================ */

(function() {
    'use strict';

    // ── DOM refs ──
    const addressInput = document.getElementById('addressInput');
    const clearBtn     = document.getElementById('clearBtn');
    const searchBtn    = document.getElementById('searchBtn');
    const loader       = document.getElementById('loader');
    const resultsEl    = document.getElementById('results');
    const noResults    = document.getElementById('noResults');

    // State
    let appData   = null;   // full API response
    let heroData  = null;   // current hero property
    let compsData = [];     // current comps list (includes the previous hero when swapped)
    let carouselIdx = 0;
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
        if (e.key === 'Enter') setTimeout(doSearch, 150);
    });

    // ── Carousel arrows ──
    document.getElementById('carouselLeft').addEventListener('click', function() { moveCarousel(-1); });
    document.getElementById('carouselRight').addEventListener('click', function() { moveCarousel(1); });

    // ── Main search ──
    function doSearch() {
        var addr = addressInput.value.trim();
        if (!addr) return;

        showLoader();
        hideResults();

        var form = new FormData();
        form.append('full_address', addr);

        fetch('api/search.php', { method: 'POST', body: form })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                hideLoader();
                if (!data.success) { showNoResults(data.error || 'Search failed.'); return; }

                appData = data;

                // Pick the best hero: subject if found, otherwise first comp
                if (data.subject) {
                    heroData = data.subject;
                    compsData = (data.comps || []).filter(function(c) { return c.ListingKey !== data.subject.ListingKey; });
                } else if (data.comps && data.comps.length > 0) {
                    heroData = data.comps[0];
                    compsData = data.comps.slice(1);
                } else {
                    showNoResults('No MLS listings found for this address.');
                    return;
                }

                renderAll();
            })
            .catch(function(err) {
                hideLoader();
                showNoResults('Network error: ' + err.message);
            });
    }

    // ── Render everything ──
    function renderAll() {
        resultsEl.classList.remove('hidden');
        noResults.classList.add('hidden');
        renderHero(heroData);
        renderMap(appData.geocoded, heroData, compsData);
        renderCompCards(compsData);
        document.getElementById('compCount').textContent = '(' + compsData.length + ' properties)';
    }

    // ═══════════════════════════════════════════════════════════
    //  HERO — Full property detail
    // ═══════════════════════════════════════════════════════════

    function renderHero(p) {
        // ── Photo Carousel ──
        var photos = p._photos || [];
        var carousel = document.getElementById('heroCarousel');
        carousel.innerHTML = '';
        carouselIdx = 0;

        if (photos.length > 0) {
            photos.forEach(function(url, i) {
                var slide = document.createElement('div');
                slide.className = 'carousel-slide' + (i === 0 ? ' active' : '');
                slide.style.backgroundImage = 'url(' + url + ')';
                carousel.appendChild(slide);
            });
            updateCarouselCounter(photos.length);
            document.getElementById('carouselLeft').classList.toggle('hidden', photos.length < 2);
            document.getElementById('carouselRight').classList.toggle('hidden', photos.length < 2);
        } else {
            carousel.innerHTML = '<div class="carousel-slide active carousel-empty">&#127968;<br><span>No Photos Available</span></div>';
            document.getElementById('carouselLeft').classList.add('hidden');
            document.getElementById('carouselRight').classList.add('hidden');
            document.getElementById('carouselCounter').textContent = '';
        }

        // ── Status Badge ──
        var status = p.StandardStatus || 'Unknown';
        var badge = document.getElementById('heroStatusBadge');
        badge.textContent = formatStatus(status);
        badge.className = 'hero-status-badge ' + statusClass(status);

        // ── Address + Price ──
        var street = [p.StreetNumber, p.StreetDirPrefix, p.StreetName, p.StreetSuffix, p.StreetDirSuffix].filter(Boolean).join(' ');
        var unit = p.UnitNumber ? ' #' + p.UnitNumber : '';
        document.getElementById('heroAddress').textContent = street + unit;
        document.getElementById('heroCityLine').textContent = [p.City, p.StateOrProvince, p.PostalCode, p.CountyOrParish ? p.CountyOrParish + ' County' : ''].filter(Boolean).join(', ');

        var price = p.ClosePrice || p.ListPrice;
        document.getElementById('heroPrice').textContent = price ? '$' + num(price) : '—';
        if (price && p.LivingArea) {
            document.getElementById('heroPriceSqft').textContent = '$' + num(Math.round(price / p.LivingArea)) + '/sq ft';
        } else {
            document.getElementById('heroPriceSqft').textContent = '';
        }

        // ── Key Stats ──
        setText('heroBeds', p.BedroomsTotal);
        setText('heroBaths', p.BathroomsTotalInteger);
        setText('heroSqft', p.LivingArea ? num(p.LivingArea) : null);
        setText('heroYear', p.YearBuilt);
        setText('heroGarage', p.GarageSpaces);
        setText('heroStories', p.StoriesTotal);
        setText('heroDom', p.DaysOnMarket);

        // Lot
        var lotText = '—';
        if (p.LotSizeAcres && p.LotSizeAcres > 0 && p.LotSizeAcres < 100) {
            lotText = p.LotSizeAcres >= 1 ? p.LotSizeAcres.toFixed(2) + ' ac' : num(Math.round(p.LotSizeAcres * 43560)) + ' sf';
        } else if (p.LotSizeSquareFeet) {
            lotText = num(p.LotSizeSquareFeet) + ' sf';
        }
        document.getElementById('heroLot').textContent = lotText;

        // ── Tags ──
        var tags = [];
        if (p.PropertyType) tags.push(p.PropertyType);
        if (p.PropertySubType) tags.push(p.PropertySubType);
        if (p.ArchitecturalStyle) tags.push(arr(p.ArchitecturalStyle));
        if (p.PoolPrivateYN === true || p.PoolPrivateYN === 'Yes') tags.push('Private Pool');
        if (p.View) tags.push('View: ' + arr(p.View));
        if (p.DirectionFaces) tags.push('Faces ' + p.DirectionFaces);
        if (p.AssociationFee) tags.push('HOA $' + num(p.AssociationFee) + '/' + (p.AssociationFeeFrequency || 'mo'));
        if (p.TaxAnnualAmount) tags.push('Tax $' + num(p.TaxAnnualAmount) + '/yr');
        if (p.ListingId) tags.push('MLS# ' + p.ListingId);

        document.getElementById('heroTags').innerHTML = tags.map(function(t) { return '<span class="tag">' + esc(t) + '</span>'; }).join('');

        // ── Agent Cards ──
        var agentsHtml = '';
        agentsHtml += agentCardHtml('Listing Agent', p.ListAgentFullName, p.ListOfficeName, p.ListAgentDirectPhone, p.ListAgentEmail, p.ListOfficePhone);
        agentsHtml += agentCardHtml('Buyer Agent', p.BuyerAgentFullName, p.BuyerOfficeName, p.BuyerAgentDirectPhone, p.BuyerAgentEmail, p.BuyerOfficePhone);
        agentsHtml += agentCardHtml('Co-List Agent', p.CoListAgentFullName, null, p.CoListAgentDirectPhone, p.CoListAgentEmail);
        agentsHtml += agentCardHtml('Showing Contact', p.ShowingContactName, p.ShowingContactType, p.ShowingContactPhone);

        document.getElementById('heroAgents').innerHTML = agentsHtml;

        // ── Property Details Grid ──
        var details = [];
        addDetail(details, 'Bedrooms', p.BedroomsTotal);
        addDetail(details, 'Bathrooms (Full)', p.BathroomsFull);
        addDetail(details, 'Bathrooms (Half)', p.BathroomsHalf);
        addDetail(details, 'Living Area', p.LivingArea ? num(p.LivingArea) + ' sq ft' : null);
        addDetail(details, 'Building Area', p.BuildingAreaTotal ? num(p.BuildingAreaTotal) + ' sq ft' : null);
        addDetail(details, 'Lot Size', lotText !== '—' ? lotText : null);
        addDetail(details, 'Year Built', p.YearBuilt);
        addDetail(details, 'Stories', p.StoriesTotal);
        addDetail(details, 'Garage Spaces', p.GarageSpaces);
        addDetail(details, 'Construction', arr(p.ConstructionMaterials));
        addDetail(details, 'Foundation', arr(p.FoundationDetails));
        addDetail(details, 'Roof', arr(p.Roof));
        addDetail(details, 'Flooring', arr(p.Flooring));
        addDetail(details, 'Heating', arr(p.Heating));
        addDetail(details, 'Cooling', arr(p.Cooling));
        addDetail(details, 'Appliances', arr(p.Appliances));
        addDetail(details, 'Interior Features', arr(p.InteriorFeatures));
        addDetail(details, 'Exterior Features', arr(p.ExteriorFeatures));
        addDetail(details, 'Patio/Porch', arr(p.PatioAndPorchFeatures));
        addDetail(details, 'Parking', arr(p.ParkingFeatures));
        addDetail(details, 'Laundry', arr(p.LaundryFeatures));
        addDetail(details, 'Fireplace', arr(p.FireplaceFeatures));
        addDetail(details, 'Fencing', arr(p.Fencing));
        addDetail(details, 'Security', arr(p.SecurityFeatures));
        addDetail(details, 'Windows', arr(p.WindowFeatures));
        addDetail(details, 'Water', arr(p.WaterSource));
        addDetail(details, 'Sewer', arr(p.Sewer));
        addDetail(details, 'Electric', arr(p.Electric));
        addDetail(details, 'Common Walls', arr(p.CommonWalls));
        addDetail(details, 'Direction Faces', p.DirectionFaces);
        addDetail(details, 'View', arr(p.View));
        addDetail(details, 'Original List Price', p.OriginalListPrice ? '$' + num(p.OriginalListPrice) : null);
        addDetail(details, 'List Price', p.ListPrice ? '$' + num(p.ListPrice) : null);
        addDetail(details, 'Close Price', p.ClosePrice ? '$' + num(p.ClosePrice) : null);
        addDetail(details, 'Close Date', p.CloseDate);
        addDetail(details, 'Tax Assessed Value', p.TaxAssessedValue ? '$' + num(p.TaxAssessedValue) : null);
        addDetail(details, 'Annual Tax', p.TaxAnnualAmount ? '$' + num(p.TaxAnnualAmount) : null);
        addDetail(details, 'HOA Fee', p.AssociationFee ? '$' + num(p.AssociationFee) + '/' + (p.AssociationFeeFrequency || 'mo') : null);

        document.getElementById('heroDetailsGrid').innerHTML = details.length > 0
            ? '<h4>Property Details</h4><div class="details-grid">' + details.join('') + '</div>'
            : '';

        // ── Public Remarks ──
        var pubSection = document.getElementById('heroPublicRemarks');
        if (p.PublicRemarks) {
            document.getElementById('heroPublicRemarksText').textContent = p.PublicRemarks;
            pubSection.classList.remove('hidden');
        } else {
            pubSection.classList.add('hidden');
        }

        // ── Private Remarks ──
        var privSection = document.getElementById('heroPrivateRemarks');
        if (p.PrivateRemarks) {
            document.getElementById('heroPrivateRemarksText').textContent = p.PrivateRemarks;
            privSection.classList.remove('hidden');
        } else {
            privSection.classList.add('hidden');
        }

        // ── Showing Instructions ──
        var metaHtml = '';
        if (p.ShowingInstructions) {
            metaHtml += '<div class="meta-row"><strong>Showing Instructions:</strong> ' + esc(p.ShowingInstructions) + '</div>';
        }
        if (p.ListingContractDate) {
            metaHtml += '<div class="meta-row"><strong>Listed:</strong> ' + esc(p.ListingContractDate) + '</div>';
        }
        if (p.CumulativeDaysOnMarket != null) {
            metaHtml += '<div class="meta-row"><strong>Cumulative DOM:</strong> ' + p.CumulativeDaysOnMarket + '</div>';
        }
        if (p.ModificationTimestamp) {
            metaHtml += '<div class="meta-row"><strong>Last Updated:</strong> ' + new Date(p.ModificationTimestamp).toLocaleDateString() + '</div>';
        }
        document.getElementById('heroMeta').innerHTML = metaHtml;

        // Scroll to top of hero
        document.getElementById('heroSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── Agent card HTML builder ──
    function agentCardHtml(role, name, office, phone, email, officePhone) {
        if (!name) return '';
        var h = '<div class="agent-card">';
        h += '<div class="agent-role">' + esc(role) + '</div>';
        h += '<div class="agent-name">' + esc(name) + '</div>';
        if (office) h += '<div class="agent-office">' + esc(office) + '</div>';
        h += '<div class="agent-contacts">';
        if (phone) {
            var clean = phone.replace(/\D/g, '');
            h += '<a href="tel:' + clean + '" class="agent-contact-link">&#128222; ' + esc(phone) + '</a>';
        }
        if (email) {
            h += '<a href="mailto:' + esc(email) + '" class="agent-contact-link">&#9993; ' + esc(email) + '</a>';
        }
        if (officePhone && officePhone !== phone) {
            var cleanOff = officePhone.replace(/\D/g, '');
            h += '<a href="tel:' + cleanOff + '" class="agent-contact-link">&#127970; Office: ' + esc(officePhone) + '</a>';
        }
        if (!phone && !email) {
            h += '<span class="agent-no-contact">No contact info available</span>';
        }
        h += '</div></div>';
        return h;
    }

    // ── Carousel navigation ──
    function moveCarousel(dir) {
        var slides = document.querySelectorAll('#heroCarousel .carousel-slide');
        if (slides.length < 2) return;
        slides[carouselIdx].classList.remove('active');
        carouselIdx = (carouselIdx + dir + slides.length) % slides.length;
        slides[carouselIdx].classList.add('active');
        updateCarouselCounter(slides.length);
    }
    function updateCarouselCounter(total) {
        document.getElementById('carouselCounter').textContent = (carouselIdx + 1) + ' / ' + total;
    }

    // ═══════════════════════════════════════════════════════════
    //  COMPS — Cards + Map
    // ═══════════════════════════════════════════════════════════

    function renderCompCards(comps) {
        var container = document.getElementById('compsList');
        container.innerHTML = '';

        comps.forEach(function(c, idx) {
            var price = c.ClosePrice || c.ListPrice;
            var photo = (c._photos && c._photos[0]) || '';
            var status = c.StandardStatus || '';

            var card = document.createElement('div');
            card.className = 'comp-card';
            card.dataset.compIdx = idx;

            var html = '';
            if (photo) {
                html += '<div class="comp-card-img" style="background-image:url(' + escAttr(photo) + ')"></div>';
            } else {
                html += '<div class="comp-card-img comp-card-img-empty">&#127968;</div>';
            }

            html += '<div class="comp-card-body">';
            html += '<div class="comp-card-status ' + statusClass(status) + '">' + formatStatus(status) + '</div>';
            html += '<div class="comp-card-price">$' + (price ? num(price) : '—') + '</div>';
            html += '<div class="comp-card-addr">' + esc(formatAddr(c)) + '</div>';

            html += '<div class="comp-card-stats">';
            html += '<div><span>' + (c.BedroomsTotal || '—') + '</span> bd</div>';
            html += '<div><span>' + (c.BathroomsTotalInteger || '—') + '</span> ba</div>';
            html += '<div><span>' + (c.LivingArea ? num(c.LivingArea) : '—') + '</span> sqft</div>';
            html += '</div>';

            if (c._distanceFt) {
                html += '<div class="comp-card-distance">' + num(c._distanceFt) + ' ft away</div>';
            }
            if (c.ListAgentFullName) {
                html += '<div class="comp-card-agent">&#128100; ' + esc(c.ListAgentFullName);
                if (c.ListAgentDirectPhone) html += ' &middot; ' + esc(c.ListAgentDirectPhone);
                html += '</div>';
            }

            html += '<div class="comp-card-swap">Click to view full details &#8594;</div>';
            html += '</div>';

            card.innerHTML = html;

            // ── SWAP: comp becomes hero, hero becomes comp ──
            card.addEventListener('click', function() {
                var clickedIdx = parseInt(this.dataset.compIdx);
                var clickedComp = compsData[clickedIdx];

                // Old hero goes into comps at the position of the clicked comp
                var oldHero = heroData;
                // Compute distance for old hero (so it shows in comp card)
                if (oldHero.Latitude && oldHero.Longitude && appData.geocoded) {
                    oldHero._distance = haversine(appData.geocoded.lat, appData.geocoded.lng, oldHero.Latitude, oldHero.Longitude);
                    oldHero._distanceFt = Math.round(oldHero._distance * 5280);
                }

                // Swap
                heroData = clickedComp;
                compsData[clickedIdx] = oldHero;

                renderAll();
            });

            container.appendChild(card);
        });
    }

    // ── Map ──
    function renderMap(geo, subject, comps) {
        var mapEl = document.getElementById('map');
        if (map) { map.remove(); map = null; }
        markers = [];

        map = L.map(mapEl).setView([geo.lat, geo.lng], 16);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OSM &copy; CARTO',
            maxZoom: 19,
        }).addTo(map);

        // 1/8 mile radius circle
        L.circle([geo.lat, geo.lng], {
            radius: 201, color: '#58a6ff', fillColor: '#58a6ff',
            fillOpacity: 0.08, weight: 1.5, dashArray: '6,4',
        }).addTo(map);

        // Subject marker
        if (subject && subject.Latitude && subject.Longitude) {
            var si = L.divIcon({
                html: '<div style="width:18px;height:18px;background:#ff6b6b;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.5);"></div>',
                iconSize: [18, 18], iconAnchor: [9, 9], className: '',
            });
            var sm = L.marker([subject.Latitude, subject.Longitude], { icon: si }).addTo(map);
            var sp = subject.ClosePrice || subject.ListPrice;
            sm.bindPopup('<b>CURRENT PROPERTY</b><br><div class="popup-price">$' + (sp ? num(sp) : '—') + '</div><div class="popup-addr">' + esc(formatAddr(subject)) + '</div>');
        }

        // Comp markers
        comps.forEach(function(c) {
            if (!c.Latitude || !c.Longitude) return;
            if (subject && c.ListingKey === subject.ListingKey) return;

            var color = statusMarkerColor(c.StandardStatus);
            var icon = L.divIcon({
                html: '<div style="width:12px;height:12px;background:' + color + ';border:2px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,0.4);"></div>',
                iconSize: [12, 12], iconAnchor: [6, 6], className: '',
            });
            var m = L.marker([c.Latitude, c.Longitude], { icon: icon }).addTo(map);
            var cp = c.ClosePrice || c.ListPrice;
            var popup = '<div class="popup-price">$' + (cp ? num(cp) : '—') + '</div>';
            popup += '<div class="popup-addr">' + esc(formatAddr(c)) + '</div>';
            popup += '<div style="margin-top:4px;font-size:12px;">' + (c.BedroomsTotal||'—') + ' bd | ' + (c.BathroomsTotalInteger||'—') + ' ba | ' + (c.LivingArea ? num(c.LivingArea) + ' sqft' : '—') + '</div>';
            if (c.ListAgentFullName) popup += '<div style="font-size:11px;color:#58a6ff;margin-top:2px;">' + esc(c.ListAgentFullName) + '</div>';
            m.bindPopup(popup);
            markers.push(m);
        });

        setTimeout(function() { map.invalidateSize(); }, 100);
    }

    // ═══════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════

    function showLoader() { loader.classList.remove('hidden'); }
    function hideLoader() { loader.classList.add('hidden'); }
    function hideResults() { resultsEl.classList.add('hidden'); noResults.classList.add('hidden'); }
    function showNoResults(msg) { document.getElementById('noResultsMsg').textContent = msg; noResults.classList.remove('hidden'); }

    function setText(id, val) { document.getElementById(id).textContent = val != null ? val : '—'; }
    function num(n) { return Number(n).toLocaleString(); }
    function formatAddr(p) {
        var street = [p.StreetNumber, p.StreetName].filter(Boolean).join(' ');
        var unit = p.UnitNumber ? ' #' + p.UnitNumber : '';
        return street + unit + ', ' + [p.City, p.StateOrProvince].filter(Boolean).join(', ');
    }

    function arr(val) {
        if (!val) return '';
        if (Array.isArray(val)) return val.join(', ');
        return String(val);
    }

    function addDetail(list, label, value) {
        if (!value && value !== 0) return;
        list.push('<div class="detail-item"><span class="detail-label">' + esc(label) + '</span><span class="detail-value">' + esc(String(value)) + '</span></div>');
    }

    function formatStatus(s) {
        var m = { 'Active':'Active','Pending':'Pending','Closed':'Closed','ActiveUnderContract':'Under Contract','ComingSoon':'Coming Soon','Canceled':'Canceled','Expired':'Expired' };
        return m[s] || s;
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

    function haversine(lat1, lng1, lat2, lng2) {
        var R = 3959, dLat = rad(lat2 - lat1), dLng = rad(lng2 - lng1);
        var a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(rad(lat1))*Math.cos(rad(lat2))*Math.sin(dLng/2)*Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }
    function rad(d) { return d * Math.PI / 180; }

    function esc(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
    function escAttr(s) { return (s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

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
