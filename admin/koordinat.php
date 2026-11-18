<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
requireAdmin();

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $exception) {
    error_log('Halaman koordinat gagal memuat database: ' . $exception->getMessage());
    http_response_code(500);
    exit('Halaman penentuan koordinat belum dapat dimuat.');
}

$errors = [];

// Proses simpan pembaruan koordinat
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrfToken();
    $idGereja = filter_input(INPUT_POST, 'id_gereja', FILTER_VALIDATE_INT);
    $latText = trim((string) ($_POST['latitude'] ?? ''));
    $lngText = trim((string) ($_POST['longitude'] ?? ''));

    if ($idGereja === false || $idGereja === null || $idGereja < 1) {
        $errors[] = 'Gereja yang dipilih tidak valid.';
    }

    $latitude = filter_var($latText, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($lngText, FILTER_VALIDATE_FLOAT);

    if ($latitude === false || $longitude === false || !is_finite((float) $latitude) || !is_finite((float) $longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        $errors[] = 'Koordinat tidak valid. Latitude harus antara -90 sampai 90 dan Longitude antara -180 sampai 180.';
    }

    if ($errors === []) {
        try {
            $stmtCheck = $pdo->prepare('SELECT nama_gereja FROM gereja WHERE id_gereja = :id');
            $stmtCheck->execute(['id' => $idGereja]);
            $churchName = $stmtCheck->fetchColumn();

            if (!$churchName) {
                $errors[] = 'Data gereja tidak ditemukan di database.';
            } else {
                $stmtUpdate = $pdo->prepare('UPDATE gereja SET latitude = :lat, longitude = :lng WHERE id_gereja = :id');
                $stmtUpdate->execute([
                    'lat' => (float) $latitude,
                    'lng' => (float) $longitude,
                    'id' => $idGereja,
                ]);

                setFlash('success', 'Titik koordinat untuk ' . (string) $churchName . ' berhasil disimpan (' . number_format((float) $latitude, 6) . ', ' . number_format((float) $longitude, 6) . ').');
                header('Location: koordinat.php?id=' . (int) $idGereja);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Gagal menyimpan koordinat: ' . $exception->getMessage());
            $errors[] = 'Gagal menyimpan koordinat ke database. Pastikan koneksi tersedia.';
        }
    }
}

// Ambil seluruh daftar gereja
$churches = $pdo->query('SELECT id_gereja, nama_gereja, alamat, kecamatan, latitude, longitude, foto FROM gereja ORDER BY nama_gereja ASC')->fetchAll();

// Tentukan gereja yang aktif dipilih
$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$selectedChurch = null;

if ($selectedId !== false && $selectedId !== null && $selectedId >= 1) {
    foreach ($churches as $item) {
        if ((int) $item['id_gereja'] === $selectedId) {
            $selectedChurch = $item;
            break;
        }
    }
}

if ($selectedChurch === null && count($churches) > 0) {
    $selectedChurch = $churches[0];
    $selectedId = (int) $selectedChurch['id_gereja'];
}

renderAdminHeader('Penentuan Koordinat', 'koordinat');
?>

<!-- Header Penentuan Koordinat -->
<div class="journal-header">
    <div class="journal-header-left">
        <h1 class="journal-title">Penentuan Koordinat Gereja</h1>
        <p class="journal-subtitle">Pemetaan Spasial Interaktif · Leaflet & OpenStreetMap</p>
    </div>
    <div class="journal-header-actions">
        <a class="button secondary" href="index.php">← Kembali ke Kelola Data</a>
        <a class="button btn-terracotta" href="../index.php" target="_blank" rel="noopener">Lihat di Peta Publik ↗</a>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="flash error" role="alert">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Layout Dua Kolom Khusus Penentuan Koordinat -->
<div class="admin-two-col" style="grid-template-columns: minmax(0, 380px) minmax(0, 1fr);">
    <!-- Kolom Kiri: Pemilih Gereja & Form Koordinat -->
    <div class="panel-card">
        <div class="panel-card-header">
            <h2 class="panel-card-title">Pilih Gereja & Simpan Koordinat</h2>
        </div>
        <div class="form-panel-body">
            <!-- Dropdown Pilihan Gereja -->
            <label for="selectGerejaOption" style="font-size: 0.86rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; display: block;">
                Pilih Gereja yang Ingin Dipetakan:
            </label>
            <select id="selectGerejaOption" class="church-select-dropdown" style="margin-bottom: 1.25rem;">
                <?php foreach ($churches as $c): ?>
                    <?php
                        $hasCoord = $c['latitude'] !== null && $c['longitude'] !== null;
                        $statusText = $hasCoord ? '✓ Sudah ada koordinat' : '⚠ Belum ada koordinat';
                    ?>
                    <option value="<?= (int) $c['id_gereja'] ?>" <?= (int) $c['id_gereja'] === (int) ($selectedChurch['id_gereja'] ?? 0) ? 'selected' : '' ?>>
                        <?= h($c['nama_gereja']) ?> (<?= h($c['kecamatan']) ?>) — <?= $statusText ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Detail Gereja Terpilih -->
            <div class="selected-church-box" style="padding: 0.85rem; border-radius: 6px; background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 1.25rem;">
                <strong style="display: block; font-size: 0.95rem; color: #0f172a;" id="displayNamaGereja">
                    <?= h($selectedChurch['nama_gereja'] ?? 'Belum memilih gereja') ?>
                </strong>
                <span style="display: block; font-size: 0.82rem; color: #64748b; margin-top: 0.2rem;" id="displayKecamatan">
                    Kecamatan <?= h($selectedChurch['kecamatan'] ?? '—') ?> · <?= h($selectedChurch['alamat'] ?? '—') ?>
                </span>
            </div>

            <!-- Form Simpan Koordinat -->
            <form method="post" action="koordinat.php?id=<?= (int) ($selectedChurch['id_gereja'] ?? 0) ?>">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id_gereja" id="formIdGereja" value="<?= (int) ($selectedChurch['id_gereja'] ?? 0) ?>">

                <label style="display: grid; gap: 0.3rem; margin-bottom: 0.85rem; font-size: 0.86rem; font-weight: 600; color: #475569;">
                    Latitude (Garis Lintang)
                    <input type="number" step="any" min="-90" max="90" name="latitude" id="coordLatitude" required
                           value="<?= h($selectedChurch['latitude'] ?? '') ?>" placeholder="Contoh: 1.173857">
                </label>

                <label style="display: grid; gap: 0.3rem; margin-bottom: 1.25rem; font-size: 0.86rem; font-weight: 600; color: #475569;">
                    Longitude (Garis Bujur)
                    <input type="number" step="any" min="-180" max="180" name="longitude" id="coordLongitude" required
                           value="<?= h($selectedChurch['longitude'] ?? '') ?>" placeholder="Contoh: 124.885146">
                </label>

                <div class="coordinate-instruction-card" style="padding: 0.75rem 0.85rem; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; font-size: 0.8rem; color: #1e40af; line-height: 1.4; margin-bottom: 1.25rem;">
                    <strong>Petunjuk:</strong> Klik langsung lokasi gereja pada peta interaktif di sebelah kanan untuk memperbarui koordinat secara otomatis, lalu tekan tombol <em>Simpan Titik Koordinat</em>.
                </div>

                <div class="form-actions-journal">
                    <button class="button btn-terracotta" type="submit" style="width: 100%;">
                        💾 Simpan Titik Koordinat
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Kolom Kanan: Peta Interaktif Leaflet Layar Penuh -->
    <div class="panel-card">
        <div class="panel-card-header">
            <h2 class="panel-card-title">Peta Sebaran & Penentuan Titik</h2>
            <span class="panel-card-sub">Klik peta untuk menempatkan atau menggeser marker</span>
        </div>
        <div class="map-panel-body" style="padding: 0; position: relative;">
            <div id="koordinat-page-map" style="height: 560px; width: 100%; border-radius: 0 0 8px 8px;"></div>
            <div class="map-helper-badge" style="bottom: 20px; left: 15px;">
                📍 Mode Penentuan Koordinat: Klik pada titik peta untuk meletakkan posisi gereja yang sedang dipilih.
            </div>
        </div>
    </div>
</div>

<!-- Leaflet Library & Script Khusus Halaman Penentuan Koordinat -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    const churches = <?= json_encode($churches, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    let selectedId = <?= (int) ($selectedChurch['id_gereja'] ?? 0) ?>;

    const selectEl = document.getElementById('selectGerejaOption');
    const formIdEl = document.getElementById('formIdGereja');
    const latInput = document.getElementById('coordLatitude');
    const lngInput = document.getElementById('coordLongitude');
    const displayNama = document.getElementById('displayNamaGereja');
    const displayKecamatan = document.getElementById('displayKecamatan');

    // Inisialisasi peta Leaflet
    const map = L.map('koordinat-page-map').setView([1.20, 124.90], 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    let activeMarker = null;
    const markersGroup = L.featureGroup().addTo(map);
    let temporaryMarker = null;
    const temporaryMarkerGroup = L.layerGroup().addTo(map);

    function getSelectedChurch() {
        return churches.find(c => Number(c.id_gereja) === Number(selectedId));
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[character]);
    }

    function renderAllMarkers() {
        markersGroup.clearLayers();
        temporaryMarkerGroup.clearLayers();
        activeMarker = null;
        temporaryMarker = null;

        churches.forEach(church => {
            const lat = Number.parseFloat(church.latitude);
            const lng = Number.parseFloat(church.longitude);
            const isSelected = Number(church.id_gereja) === Number(selectedId);

            if (Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                const marker = L.marker([lat, lng]);

                if (isSelected) {
                    activeMarker = marker;
                    marker.bindTooltip(`<strong>${escapeHtml(church.nama_gereja)}</strong><br><span style="color:#38bdf8;">${lat.toFixed(6)}, ${lng.toFixed(6)}</span>`, {
                        permanent: true,
                        direction: 'top',
                        className: 'admin-marker-tooltip',
                        offset: [0, -12]
                    }).openTooltip();
                } else {
                    marker.bindTooltip(`${escapeHtml(church.nama_gereja)} (${escapeHtml(church.kecamatan)})`, {
                        direction: 'top'
                    });
                }

                marker.on('click', () => {
                    selectChurchById(church.id_gereja);
                });

                markersGroup.addLayer(marker);
            }
        });

        // Jika gereja terpilih belum memiliki koordinat tersimpan tapi ada input aktif
        const currLat = Number.parseFloat(latInput.value);
        const currLng = Number.parseFloat(lngInput.value);
        if (!activeMarker && Number.isFinite(currLat) && Number.isFinite(currLng)) {
            const currChurch = getSelectedChurch();
            temporaryMarker = L.marker([currLat, currLng]).addTo(temporaryMarkerGroup);
            temporaryMarker.bindTooltip(`<strong>${escapeHtml(currChurch ? currChurch.nama_gereja : 'Titik Baru')}</strong><br><span style="color:#38bdf8;">${currLat.toFixed(6)}, ${currLng.toFixed(6)}</span>`, {
                permanent: true,
                direction: 'top',
                className: 'admin-marker-tooltip',
                offset: [0, -12]
            }).openTooltip();
        }
    }

    function selectChurchById(id) {
        selectedId = Number(id);
        if (selectEl) selectEl.value = String(selectedId);
        if (formIdEl) formIdEl.value = String(selectedId);

        const church = getSelectedChurch();
        if (church) {
            if (displayNama) displayNama.textContent = church.nama_gereja;
            if (displayKecamatan) displayKecamatan.textContent = `Kecamatan ${church.kecamatan} · ${church.alamat || '—'}`;

            if (church.latitude && church.longitude) {
                latInput.value = Number(church.latitude).toFixed(7);
                lngInput.value = Number(church.longitude).toFixed(7);
                map.setView([Number(church.latitude), Number(church.longitude)], 15);
            } else {
                latInput.value = '';
                lngInput.value = '';
            }
        }
        renderAllMarkers();
    }

    if (selectEl) {
        selectEl.addEventListener('change', (e) => {
            selectChurchById(e.target.value);
        });
    }

    // Klik pada peta untuk menaruh atau menggeser titik koordinat
    map.on('click', (e) => {
        const { lat, lng } = e.latlng;
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);

        const church = getSelectedChurch();
        const churchName = church ? church.nama_gereja : 'Gereja Terpilih';

        if (activeMarker) {
            activeMarker.setLatLng([lat, lng]);
            activeMarker.setTooltipContent(`<strong>${escapeHtml(churchName)}</strong><br><span style="color:#38bdf8;">${lat.toFixed(6)}, ${lng.toFixed(6)}</span>`);
        } else if (temporaryMarker) {
            temporaryMarker.setLatLng([lat, lng]);
            temporaryMarker.setTooltipContent(`<strong>${escapeHtml(churchName)}</strong><br><span style="color:#38bdf8;">${lat.toFixed(6)}, ${lng.toFixed(6)}</span>`);
        } else {
            temporaryMarker = L.marker([lat, lng]).addTo(temporaryMarkerGroup);
            temporaryMarker.bindTooltip(`<strong>${escapeHtml(churchName)}</strong><br><span style="color:#38bdf8;">${lat.toFixed(6)}, ${lng.toFixed(6)}</span>`, {
                permanent: true,
                direction: 'top',
                className: 'admin-marker-tooltip',
                offset: [0, -12]
            }).openTooltip();
        }
    });

    renderAllMarkers();

    // Zoom ke titik aktif jika ada
    const activeChurch = getSelectedChurch();
    if (activeChurch && activeChurch.latitude && activeChurch.longitude) {
        map.setView([Number(activeChurch.latitude), Number(activeChurch.longitude)], 15);
    } else if (markersGroup.getLayers().length > 0) {
        map.fitBounds(markersGroup.getBounds().pad(0.15));
    }

    setTimeout(() => map.invalidateSize(), 250);
    window.addEventListener('resize', () => map.invalidateSize());
})();
</script>

<?php renderAdminFooter(); ?>
