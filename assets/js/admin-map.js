(function () {
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const mapLatInput = document.getElementById('map-lat-input');
    const mapLngInput = document.getElementById('map-lng-input');
    const btnApplyCoords = document.getElementById('btn-apply-coords');
    const churchNameInput = document.querySelector('input[name="nama_gereja"]');
    const mapContainer = document.getElementById('coordinate-map');

    if (!mapContainer) return;

    const coordinateMap = L.map('coordinate-map').setView([1.20, 124.90], 10);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(coordinateMap);

    let locationMarker = null;

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[m]);
    }

    function readCoordinates() {
        if (!latitudeInput || !longitudeInput) return null;
        const latitude = Number.parseFloat(latitudeInput.value);
        const longitude = Number.parseFloat(longitudeInput.value);
        return Number.isFinite(latitude) && Number.isFinite(longitude)
            && latitude >= -90 && latitude <= 90 && longitude >= -180 && longitude <= 180
            ? [latitude, longitude] : null;
    }

    function getTooltipHtml(coords) {
        const name = churchNameInput && churchNameInput.value.trim() !== ''
            ? churchNameInput.value.trim()
            : 'GSJA Kabupaten Minahasa';
        return `<strong>${escapeHtml(name)}</strong><span>${coords[0].toFixed(6)}, ${coords[1].toFixed(6)}</span>`;
    }

    function showMarker(coordinates, moveMap) {
        if (locationMarker) {
            locationMarker.setLatLng(coordinates);
        } else {
            locationMarker = L.marker(coordinates).addTo(coordinateMap);
        }

        locationMarker.unbindTooltip();
        locationMarker.bindTooltip(getTooltipHtml(coordinates), {
            permanent: true,
            direction: 'top',
            className: 'admin-marker-tooltip',
            offset: [0, -12]
        }).openTooltip();

        if (moveMap) {
            coordinateMap.setView(coordinates, 15);
        }

        if (mapLatInput) mapLatInput.value = coordinates[0].toFixed(7);
        if (mapLngInput) mapLngInput.value = coordinates[1].toFixed(7);
    }

    const initialCoordinates = readCoordinates();
    if (initialCoordinates) {
        showMarker(initialCoordinates, true);
    }

    coordinateMap.on('click', (event) => {
        const { lat, lng } = event.latlng;
        const latStr = lat.toFixed(7);
        const lngStr = lng.toFixed(7);
        if (latitudeInput) latitudeInput.value = latStr;
        if (longitudeInput) longitudeInput.value = lngStr;
        if (mapLatInput) mapLatInput.value = latStr;
        if (mapLngInput) mapLngInput.value = lngStr;
        showMarker([lat, lng], false);
    });

    function updateMarkerFromInputs() {
        const coordinates = readCoordinates();
        if (coordinates) {
            showMarker(coordinates, false);
        }
    }

    if (latitudeInput) latitudeInput.addEventListener('change', updateMarkerFromInputs);
    if (longitudeInput) longitudeInput.addEventListener('change', updateMarkerFromInputs);
    if (latitudeInput) latitudeInput.addEventListener('input', updateMarkerFromInputs);
    if (longitudeInput) longitudeInput.addEventListener('input', updateMarkerFromInputs);

    if (churchNameInput) {
        churchNameInput.addEventListener('input', () => {
            const coords = readCoordinates();
            if (coords && locationMarker) {
                locationMarker.setTooltipContent(getTooltipHtml(coords));
            }
        });
    }

    if (btnApplyCoords) {
        btnApplyCoords.addEventListener('click', () => {
            const lat = Number.parseFloat(mapLatInput ? mapLatInput.value : '');
            const lng = Number.parseFloat(mapLngInput ? mapLngInput.value : '');
            if (Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                if (latitudeInput) latitudeInput.value = lat.toFixed(7);
                if (longitudeInput) longitudeInput.value = lng.toFixed(7);
                showMarker([lat, lng], true);
            } else {
                alert('Nilai koordinat tidak valid. Harap periksa kembali latitude (-90 s/d 90) dan longitude (-180 s/d 180).');
            }
        });
    }

    // Invalidate size to ensure proper tile rendering in CSS Grid
    setTimeout(() => {
        coordinateMap.invalidateSize();
    }, 250);
    window.addEventListener('resize', () => {
        coordinateMap.invalidateSize();
    });

    // Client-side search & filter for table rows
    const searchInput = document.getElementById('searchTableGereja');
    const districtSelect = document.getElementById('filterTableKecamatan');
    const tableRows = document.querySelectorAll('.data-table tbody tr');

    function filterTableRows() {
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const district = (districtSelect ? districtSelect.value : '').trim();
        tableRows.forEach((row) => {
            const nameEl = row.querySelector('.col-nama');
            const districtEl = row.querySelector('.col-kecamatan');
            const nameText = (nameEl ? nameEl.textContent : '').toLowerCase();
            const districtText = (districtEl ? districtEl.textContent : '').trim();
            const matchesQuery = query === '' || nameText.includes(query);
            const matchesDistrict = district === '' || districtText === district;
            row.style.display = matchesQuery && matchesDistrict ? '' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterTableRows);
    if (districtSelect) districtSelect.addEventListener('change', filterTableRows);
})();
