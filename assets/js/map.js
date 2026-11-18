/**
 * WebGIS Pemetaan Gereja GSJA - Logika Peta dan Antarmuka Publik
 * Mengacu pada Gambar 2 Jurnal:
 * "Pengembangan WebGIS Pemetaan Gereja GSJA Studi Kasus: Sulut 2 Wilayah 2 Kabupaten Minahasa"
 */

(function () {
    'use strict';

    // Inisialisasi Peta Leaflet berpusat di Minahasa (Danau Tondano & sekitarnya)
    const map = L.map('map', {
        zoomControl: true,
        attributionControl: true
    }).setView([1.24, 124.91], 11);

    // Layer Peta Dasar OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
        updateWhenIdle: true,
        updateWhenZooming: false,
        keepBuffer: 2
    }).addTo(map);

    // Group layer untuk marker persebaran gereja
    const markersLayer = L.featureGroup().addTo(map);

    // Elemen DOM
    const searchInput = document.getElementById('search-gereja');
    const searchResults = document.getElementById('search-results');
    const btnClearSearch = document.getElementById('btn-clear-search');
    const districtFilter = document.getElementById('filter-kecamatan');
    const districtPills = document.getElementById('district-pills');
    const churchCount = document.getElementById('church-count');
    const churchTotal = document.getElementById('church-total');
    const countLabel = document.getElementById('count-label');
    const mapStatus = document.getElementById('map-status');
    const churchList = document.getElementById('church-list');
    const churchListContainer = document.getElementById('church-list-container');
    const churchListEmpty = document.getElementById('church-list-empty');
    const emptyStateMessage = document.getElementById('empty-state-message');
    const btnResetFilters = document.getElementById('btn-reset-filters');
    const legendChurchCount = document.getElementById('legend-church-count');
    const mobileBadgeCount = document.getElementById('mobile-badge-count');

    // Sidebar & Navigasi
    const sidebarPanel = document.getElementById('sidebar-panel');
    const btnCloseSidebar = document.getElementById('btn-close-sidebar');
    const btnMobileSidebar = document.getElementById('btn-mobile-sidebar');
    const navBtnMap = document.getElementById('nav-btn-map');
    const navBtnList = document.getElementById('nav-btn-list');
    const navBtnAbout = document.getElementById('nav-btn-about');
    const aboutModal = document.getElementById('about-modal');
    const btnCloseAbout = document.getElementById('btn-close-about');
    const btnConfirmAbout = document.getElementById('btn-confirm-about');

    // State data
    let allChurches = [];       // Seluruh data gereja dalam database
    let displayedChurches = []; // Data gereja setelah filter kecamatan & pencarian
    let activeChurchId = null;  // ID gereja yang sedang dipilih
    let requestSeq = 0;

    /**
     * Helper escaping HTML untuk keamanan XSS
     */
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[c]);
    }

    /**
     * Helper ekstrak desa/kelurahan dari alamat
     */
    function extractDesa(alamat) {
        if (!alamat) return '—';
        const parts = String(alamat).split(',');
        const first = parts[0].trim();
        const cleaned = first.replace(/^(Desa|Kelurahan)\s+/i, '').trim();
        return cleaned || first;
    }

    /**
     * Validasi koordinat numerik
     */
    function hasValidCoordinates(church) {
        return church &&
            Number.isFinite(church.latitude) &&
            Number.isFinite(church.longitude) &&
            church.latitude >= -90 && church.latitude <= 90 &&
            church.longitude >= -180 && church.longitude <= 180;
    }

    /**
     * Membuat ikon penanda merah khusus bergambar salib (Gambar 2 Jurnal)
     */
    function createChurchIcon(isSelected = false) {
        const pinColor = isSelected ? '#b91c1c' : '#dc2626';
        const svgPin = `
            <div class="pin-badge-wrap">
                <svg class="pin-svg-bg" viewBox="0 0 24 30" width="24" height="30" fill="none">
                    <path d="M12 1C6.477 1 2 5.477 2 11c0 7.5 10 18 10 18s10-10.5 10-18c0-5.523-4.477-10-10-10z"
                          fill="${pinColor}" stroke="#ffffff" stroke-width="1.5"/>
                </svg>
                <svg class="pin-icon-cross" viewBox="0 0 24 24" width="11" height="11" fill="#ffffff">
                    <path d="M10 2h4v5h5v4h-5v11h-4V11H5V7h5V2z"/>
                </svg>
            </div>
        `;

        return L.divIcon({
            className: 'custom-church-pin leaflet-marker-icon',
            html: svgPin,
            iconSize: [24, 30],
            iconAnchor: [12, 30],
            popupAnchor: [0, -28]
        });
    }

    /**
     * Konten pop-up informasi profil gereja (Gambar 2 Jurnal)
     */
    function popupContent(church) {
        const desa = church.desa_kelurahan || extractDesa(church.alamat);
        const mediaHtml = church.foto
            ? `<div class="popup-media-container"><img class="popup-photo-img popup-photo" src="${escapeHtml(church.foto)}" alt="Foto ${escapeHtml(church.nama)}" loading="lazy"></div>`
            : `<div class="popup-media-container">
                    <div class="popup-photo-placeholder">
                        <svg class="placeholder-icon" viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                            <path d="M11 2h2v5h5v2h-5v13h-2V9H6V7h5V2z"/>
                        </svg>
                        <span class="placeholder-label">Foto gereja belum tersedia</span>
                        <span class="placeholder-badge">Wilayah 2 · Minahasa</span>
                    </div>
               </div>`;

        return `
            <article class="church-popup church-popup-card">
                ${mediaHtml}
                <div class="popup-info-body">
                    <h3 class="popup-church-name">${escapeHtml(church.nama)}</h3>
                    <table class="popup-details-table">
                        <tr>
                            <th>Desa/Kelurahan:</th>
                            <td>${escapeHtml(desa)}</td>
                        </tr>
                        <tr>
                            <th>Kecamatan:</th>
                            <td>${escapeHtml(church.kecamatan)}</td>
                        </tr>
                        <tr>
                            <th>Kabupaten:</th>
                            <td>${escapeHtml(church.kabupaten || 'Minahasa')}</td>
                        </tr>
                        <tr>
                            <th>Alamat:</th>
                            <td>${escapeHtml(church.alamat || 'Alamat belum tersedia')}</td>
                        </tr>
                        <tr>
                            <th>Koordinat:</th>
                            <td><span class="popup-coords-text">Lat: ${church.latitude}, Long: ${church.longitude}</span></td>
                        </tr>
                    </table>
                </div>
            </article>
        `;
    }

    /**
     * Render seluruh marker gereja pada peta
     */
    function renderMarkers() {
        markersLayer.clearLayers();
        const mapped = displayedChurches.filter(hasValidCoordinates);

        mapped.forEach((church) => {
            const isSelected = church.id === activeChurchId;
            const marker = L.marker([church.latitude, church.longitude], {
                icon: createChurchIcon(isSelected),
                title: church.nama,
                riseOnHover: true
            }).bindPopup(popupContent(church), {
                maxWidth: 300,
                className: 'custom-leaflet-popup'
            });

            marker.churchId = church.id;
            marker.on('click', () => {
                highlightChurchItem(church.id);
            });

            markersLayer.addLayer(marker);
        });

        // Sesuaikan batas peta (fitBounds)
        if (mapped.length > 0) {
            map.fitBounds(markersLayer.getBounds().pad(0.12), { maxZoom: 14, animate: false });
            const noCoords = displayedChurches.length - mapped.length;
            mapStatus.textContent = noCoords > 0
                ? `${mapped.length} gereja ditampilkan pada peta (${noCoords} belum berkoordinat valid).`
                : `${mapped.length} gereja ditampilkan pada peta.`;
        } else if (displayedChurches.length > 0) {
            mapStatus.textContent = 'Data gereja tersedia, tetapi belum ada titik koordinat valid.';
        } else {
            mapStatus.textContent = 'Tidak ada gereja yang sesuai filter.';
        }

        // Perbarui angka legenda dan mobile badge
        if (legendChurchCount) legendChurchCount.textContent = String(mapped.length);
        if (mobileBadgeCount) mobileBadgeCount.textContent = String(displayedChurches.length);
    }

    /**
     * Render daftar gereja pada panel sebelah kiri
     */
    function renderChurchList() {
        churchList.replaceChildren();

        if (displayedChurches.length === 0) {
            churchListEmpty.hidden = false;
            const query = searchInput.value.trim();
            const district = districtFilter.value;
            if (query && district) {
                emptyStateMessage.textContent = `Tidak ada gereja di Kecamatan ${district} yang cocok dengan "${query}".`;
            } else if (query) {
                emptyStateMessage.textContent = `Tidak ada gereja yang cocok dengan kata kunci "${query}".`;
            } else if (district) {
                emptyStateMessage.textContent = `Tidak ada data gereja terdaftar di Kecamatan ${district}.`;
            } else {
                emptyStateMessage.textContent = 'Belum ada data gereja di dalam database.';
            }
            return;
        }

        churchListEmpty.hidden = true;

        displayedChurches.forEach((church, index) => {
            const desa = church.desa_kelurahan || extractDesa(church.alamat);
            const card = document.createElement('div');
            card.className = `church-card ${church.id === activeChurchId ? 'active' : ''}`;
            card.setAttribute('role', 'listitem');
            card.setAttribute('tabindex', '0');
            card.dataset.id = String(church.id);

            card.innerHTML = `
                <div class="church-card-number">${index + 1}</div>
                <div class="church-card-info">
                    <h3 class="church-card-name">${escapeHtml(church.nama)}</h3>
                    <p class="church-card-location">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>
                        </svg>
                        <span>${escapeHtml(desa)}, Kec. ${escapeHtml(church.kecamatan)}</span>
                    </p>
                </div>
                <div class="church-card-action" title="Lihat lokasi di peta">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
                    </svg>
                </div>
            `;

            card.addEventListener('click', () => {
                selectChurch(church);
            });

            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    selectChurch(church);
                }
            });

            churchList.appendChild(card);
        });
    }

    /**
     * Hitung jumlah gereja per kecamatan untuk tombol pill filter
     */
    function updateDistrictPillCounts(churchesData) {
        const counts = {
            'Kakas': 0,
            'Eris': 0,
            'Tondano Barat': 0,
            'Tondano Timur': 0,
            'Tondano Utara': 0
        };

        churchesData.forEach((c) => {
            if (counts[c.kecamatan] !== undefined) {
                counts[c.kecamatan]++;
            }
        });

        const setPillText = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(val);
        };

        setPillText('pill-count-kakas', counts['Kakas']);
        setPillText('pill-count-eris', counts['Eris']);
        setPillText('pill-count-tondano-barat', counts['Tondano Barat']);
        setPillText('pill-count-tondano-timur', counts['Tondano Timur']);
        setPillText('pill-count-tondano-utara', counts['Tondano Utara']);
    }

    /**
     * Sinkronisasi status aktif tombol pill filter kecamatan
     */
    function syncDistrictPills(activeDistrict) {
        const buttons = districtPills.querySelectorAll('.pill-btn');
        buttons.forEach((btn) => {
            const district = btn.dataset.kecamatan || '';
            if (district === activeDistrict) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    /**
     * Sorot kartu gereja pada daftar sebelah kiri
     */
    function highlightChurchItem(id) {
        activeChurchId = id;
        const cards = churchList.querySelectorAll('.church-card');
        cards.forEach((card) => {
            if (card.dataset.id === String(id)) {
                card.classList.add('active');
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                card.classList.remove('active');
            }
        });
    }

    /**
     * Pilih gereja: arahkan peta ke koordinat dan buka pop-up
     */
    function selectChurch(church) {
        searchResults.hidden = true;
        highlightChurchItem(church.id);

        if (!hasValidCoordinates(church)) {
            mapStatus.textContent = `Koordinat ${church.nama} belum tersedia atau tidak valid.`;
            return;
        }

        map.setView([church.latitude, church.longitude], 16, { animate: true });

        // Temukan marker yang sesuai dan buka pop-up
        const targetMarker = markersLayer.getLayers().find((item) => item.churchId === church.id);
        if (targetMarker) {
            targetMarker.openPopup();
        }

        // Di layar mobile kecil, tutup sidebar otomatis agar peta terlihat
        if (window.innerWidth <= 768) {
            sidebarPanel.classList.remove('open');
        }
    }

    /**
     * Filter data gereja berdasarkan input pencarian dan kecamatan
     */
    function applyFilters() {
        const query = searchInput.value.trim().toLocaleLowerCase('id');
        const selectedDistrict = districtFilter.value;

        // Tampilkan/sembunyikan tombol clear search
        if (btnClearSearch) {
            btnClearSearch.hidden = query.length === 0;
        }

        // Filter dari allChurches
        displayedChurches = allChurches.filter((church) => {
            const matchDistrict = !selectedDistrict || church.kecamatan === selectedDistrict;
            const matchName = !query || church.nama.toLocaleLowerCase('id').includes(query);
            return matchDistrict && matchName;
        });

        renderMarkers();
        renderChurchList();
        renderSearchResultsDropdown(query);

        // Perbarui angka ringkasan
        churchCount.textContent = String(displayedChurches.length);
        if (selectedDistrict) {
            countLabel.textContent = `gereja di ${selectedDistrict}`;
        } else {
            countLabel.textContent = 'gereja dalam database';
        }
    }

    /**
     * Render autocomplete dropdown di bawah input cari gereja
     */
    function renderSearchResultsDropdown(query) {
        searchResults.replaceChildren();

        if (!query) {
            searchResults.hidden = true;
            return;
        }

        const matches = displayedChurches.slice(0, 8);
        if (matches.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'search-empty-msg';
            empty.textContent = 'Gereja tidak ditemukan pada data yang ditampilkan.';
            searchResults.appendChild(empty);
        } else {
            matches.forEach((church) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'search-result-item';
                btn.setAttribute('role', 'option');
                btn.innerHTML = `
                    <span class="search-result-name">${escapeHtml(church.nama)}</span>
                    <span class="search-result-sub">${escapeHtml(church.desa_kelurahan || extractDesa(church.alamat))} · ${escapeHtml(church.kecamatan)}</span>
                `;
                btn.addEventListener('click', () => {
                    searchInput.value = church.nama;
                    selectChurch(church);
                });
                searchResults.appendChild(btn);
            });
        }

        searchResults.hidden = false;
    }

    /**
     * Memuat data gereja dari API backend (api/gereja.php)
     */
    async function loadChurches() {
        const curReq = ++requestSeq;
        const district = districtFilter.value;
        mapStatus.textContent = 'Memuat data gereja...';

        const params = new URLSearchParams();
        if (district) params.set('kecamatan', district);

        try {
            const response = await fetch(`api/gereja.php${params.size ? `?${params}` : ''}`, {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'Gagal memuat data dari API.');
            }
            if (curReq !== requestSeq) return;

            const fetchedChurches = Array.isArray(payload.gereja) ? payload.gereja : [];

            // Jika memuat semua, update allChurches dan hitungan pill
            if (!district && !searchInput.value.trim()) {
                allChurches = fetchedChurches;
                updateDistrictPillCounts(allChurches);
                if (churchTotal) churchTotal.textContent = String(payload.total ?? allChurches.length);
            } else if (allChurches.length === 0) {
                allChurches = fetchedChurches;
            }

            displayedChurches = fetchedChurches;
            syncDistrictPills(district);

            // Jika ada query di kotak pencarian, saring kembali
            const query = searchInput.value.trim().toLocaleLowerCase('id');
            if (query) {
                displayedChurches = displayedChurches.filter((c) =>
                    c.nama.toLocaleLowerCase('id').includes(query)
                );
            }

            renderMarkers();
            renderChurchList();

            churchCount.textContent = String(displayedChurches.length);
            if (district) {
                countLabel.textContent = `gereja di ${district}`;
            } else {
                countLabel.textContent = 'gereja dalam database';
            }
        } catch (error) {
            if (curReq !== requestSeq) return;
            allChurches = [];
            displayedChurches = [];
            markersLayer.clearLayers();
            churchCount.textContent = '0';
            countLabel.textContent = 'data belum dapat dimuat';
            mapStatus.textContent = error.message || 'Gagal terhubung ke database gereja.';
            searchResults.hidden = true;
            renderChurchList();
        }
    }

    // =========================================================================
    // Event Listeners
    // =========================================================================

    // Input Pencarian
    searchInput.addEventListener('input', () => {
        applyFilters();
    });

    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim().length > 0) {
            renderSearchResultsDropdown(searchInput.value.trim().toLocaleLowerCase('id'));
        }
    });

    if (btnClearSearch) {
        btnClearSearch.addEventListener('click', () => {
            searchInput.value = '';
            btnClearSearch.hidden = true;
            applyFilters();
            searchInput.focus();
        });
    }

    // Filter Dropdown Kecamatan
    districtFilter.addEventListener('change', () => {
        const district = districtFilter.value;
        syncDistrictPills(district);
        loadChurches();
    });

    // Pills Filter Kecamatan
    if (districtPills) {
        districtPills.addEventListener('click', (e) => {
            const btn = e.target.closest('.pill-btn');
            if (!btn) return;
            const district = btn.dataset.kecamatan || '';
            districtFilter.value = district;
            syncDistrictPills(district);
            loadChurches();
        });
    }

    // Tombol Reset Filter
    if (btnResetFilters) {
        btnResetFilters.addEventListener('click', () => {
            searchInput.value = '';
            if (btnClearSearch) btnClearSearch.hidden = true;
            districtFilter.value = '';
            syncDistrictPills('');
            loadChurches();
        });
    }

    // Tutup dropdown pencarian saat klik di luar
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.search-field-group')) {
            searchResults.hidden = true;
        }
    });

    // Navigasi & Mobile Sidebar
    if (btnMobileSidebar) {
        btnMobileSidebar.addEventListener('click', () => {
            sidebarPanel.classList.add('open');
        });
    }

    if (btnCloseSidebar) {
        btnCloseSidebar.addEventListener('click', () => {
            sidebarPanel.classList.remove('open');
        });
    }

    if (navBtnList) {
        navBtnList.addEventListener('click', () => {
            sidebarPanel.classList.add('open');
            if (window.innerWidth <= 768) {
                searchInput.focus();
            } else {
                churchListContainer.scrollTo({ top: 0, behavior: 'smooth' });
                churchListContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                searchInput.focus({ preventScroll: true });
            }
        });
    }

    if (navBtnMap) {
        navBtnMap.addEventListener('click', () => {
            sidebarPanel.classList.remove('open');
            if (markersLayer.getLayers().length > 0) {
                map.fitBounds(markersLayer.getBounds().pad(0.12), { maxZoom: 14 });
            }
        });
    }

    // Modal Dialog Tentang
    function openAboutModal() {
        aboutModal.hidden = false;
        btnConfirmAbout.focus();
    }

    function closeAboutModal() {
        aboutModal.hidden = true;
    }

    if (navBtnAbout) {
        navBtnAbout.addEventListener('click', openAboutModal);
    }
    if (btnCloseAbout) {
        btnCloseAbout.addEventListener('click', closeAboutModal);
    }
    if (btnConfirmAbout) {
        btnConfirmAbout.addEventListener('click', closeAboutModal);
    }

    aboutModal.addEventListener('click', (e) => {
        if (e.target === aboutModal) closeAboutModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (!aboutModal.hidden) closeAboutModal();
            if (searchResults && !searchResults.hidden) searchResults.hidden = true;
            if (sidebarPanel.classList.contains('open') && window.innerWidth <= 768) {
                sidebarPanel.classList.remove('open');
            }
        }
    });

    // Muat data awal
    loadChurches();
})();
