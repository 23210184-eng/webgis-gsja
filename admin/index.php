<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
requireAdmin();

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $exception) {
    error_log('Dashboard admin gagal memuat database: ' . $exception->getMessage());
    http_response_code(500);
    exit('Dashboard belum dapat dimuat. Periksa koneksi database.');
}

// Helper untuk mengekstrak nama desa/kelurahan dari field alamat
function extractDesa(string $alamat): string
{
    $alamat = trim($alamat);
    if ($alamat === '') {
        return '—';
    }
    $parts = explode(',', $alamat);
    $first = trim($parts[0]);
    $cleaned = preg_replace('/^(Desa|Kelurahan)\s+/i', '', $first);
    return $cleaned !== '' ? $cleaned : $first;
}

// Cek apakah sedang dalam mode edit (Ubah)
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = $id !== false && $id !== null && $id >= 1;

$church = [
    'nama_gereja' => '', 'alamat' => '', 'kecamatan' => '',
    'kabupaten' => 'Minahasa', 'daerah' => 'Sulawesi Utara 2',
    'wilayah' => 'Wilayah 2', 'latitude' => '', 'longitude' => '', 'foto' => null,
];
$desaValue = '';
$errors = [];
$uploadedPath = null;

if ($isEdit) {
    try {
        $statement = $pdo->prepare('SELECT * FROM gereja WHERE id_gereja = :id');
        $statement->execute(['id' => $id]);
        $existing = $statement->fetch();
        if ($existing) {
            $church = $existing;
            $desaValue = extractDesa((string) $church['alamat']);
        } else {
            $isEdit = false;
            $id = null;
        }
    } catch (Throwable $exception) {
        error_log('Gagal memuat data untuk edit di index: ' . $exception->getMessage());
        $isEdit = false;
    }
}

// Proses submit formulir Tambah atau Ubah data
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['form_action'])) {
    verifyCsrfToken();
    foreach (['nama_gereja', 'alamat', 'kecamatan', 'kabupaten', 'daerah', 'wilayah', 'latitude', 'longitude'] as $field) {
        $church[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $desaValue = trim((string) ($_POST['desa_kelurahan'] ?? ''));

    // Jika alamat kosong tapi desa_kelurahan diisi, lengkapi alamat secara otomatis
    if ($church['alamat'] === '' && $desaValue !== '') {
        $church['alamat'] = $desaValue . ', Kec. ' . $church['kecamatan'];
    }

    $limits = ['nama_gereja' => 150, 'kecamatan' => 100, 'kabupaten' => 100, 'daerah' => 100, 'wilayah' => 100];
    if ($church['nama_gereja'] === '' || mb_strlen($church['nama_gereja']) > $limits['nama_gereja']) {
        $errors[] = 'Nama gereja wajib diisi (maksimal 150 karakter).';
    }
    if ($church['alamat'] === '' || mb_strlen($church['alamat']) > 5000) {
        $errors[] = 'Alamat wajib diisi (maksimal 5000 karakter).';
    }
    if (!in_array($church['kecamatan'], allowedDistricts(), true)) {
        $errors[] = 'Pilih salah satu kecamatan penelitian.';
    }
    foreach (['kabupaten', 'daerah', 'wilayah'] as $field) {
        if ($church[$field] === '' || mb_strlen($church[$field]) > $limits[$field]) {
            $errors[] = ucfirst($field) . ' wajib diisi (maksimal 100 karakter).';
        }
    }

    $latText = $church['latitude'];
    $lngText = $church['longitude'];
    $latitude = $latText === '' ? null : filter_var($latText, FILTER_VALIDATE_FLOAT);
    $longitude = $lngText === '' ? null : filter_var($lngText, FILTER_VALIDATE_FLOAT);
    if (($latText === '') !== ($lngText === '')) {
        $errors[] = 'Latitude dan longitude harus diisi bersama-sama.';
    } elseif ($latText !== '' && ($latitude === false || $longitude === false || !is_finite((float) $latitude) || !is_finite((float) $longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180)) {
        $errors[] = 'Koordinat tidak valid. Latitude harus -90 sampai 90 dan longitude -180 sampai 180.';
    }

    $file = $_FILES['foto'] ?? null;
    $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (is_array($file) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Foto gagal diunggah.';
        } elseif ($file['size'] < 1 || $file['size'] > 4 * 1024 * 1024) {
            $errors[] = 'Ukuran foto maksimal 4 MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $imageInfo = @getimagesize($file['tmp_name']);
            if (!isset($mimeExtensions[$mime]) || !$imageInfo || ($imageInfo['mime'] ?? '') !== $mime) {
                $errors[] = 'Format foto harus JPG, JPEG, PNG, atau WEBP yang valid.';
            } else {
                $filename = bin2hex(random_bytes(16)) . '.' . $mimeExtensions[$mime];
                $destination = __DIR__ . '/../uploads/gereja/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'Foto tidak dapat disimpan. Periksa izin folder uploads/gereja.';
                } else {
                    $uploadedPath = 'uploads/gereja/' . $filename;
                }
            }
        }
    }

    if ($errors === []) {
        $oldPhoto = safePhotoPath($church['foto'] ?? null);
        $photo = $uploadedPath ?? $oldPhoto;
        $values = [
            'nama_gereja' => $church['nama_gereja'],
            'alamat' => $church['alamat'],
            'kecamatan' => $church['kecamatan'],
            'kabupaten' => $church['kabupaten'],
            'daerah' => $church['daerah'],
            'wilayah' => $church['wilayah'],
            'latitude' => $latitude === null ? null : (float) $latitude,
            'longitude' => $longitude === null ? null : (float) $longitude,
            'foto' => $photo,
        ];
        try {
            if ($isEdit && $id !== null) {
                $values['id'] = $id;
                $stmt = $pdo->prepare('UPDATE gereja SET nama_gereja = :nama_gereja, alamat = :alamat, kecamatan = :kecamatan, kabupaten = :kabupaten, daerah = :daerah, wilayah = :wilayah, latitude = :latitude, longitude = :longitude, foto = :foto WHERE id_gereja = :id');
                $stmt->execute($values);
            } else {
                $stmt = $pdo->prepare('INSERT INTO gereja (nama_gereja, alamat, kecamatan, kabupaten, daerah, wilayah, latitude, longitude, foto) VALUES (:nama_gereja, :alamat, :kecamatan, :kabupaten, :daerah, :wilayah, :latitude, :longitude, :foto)');
                $stmt->execute($values);
            }
            if ($uploadedPath !== null && $oldPhoto !== null) {
                $oldFile = __DIR__ . '/../' . $oldPhoto;
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            setFlash('success', $isEdit ? 'Data gereja berhasil diperbarui.' : 'Data gereja berhasil ditambahkan.');
            header('Location: index.php');
            exit;
        } catch (Throwable $exception) {
            if ($uploadedPath !== null) {
                @unlink(__DIR__ . '/../' . $uploadedPath);
            }
            error_log('Simpan data gereja di dashboard gagal: ' . $exception->getMessage());
            $errors[] = 'Data belum dapat disimpan. Periksa database dan log PHP.';
        }
    } elseif ($uploadedPath !== null) {
        @unlink(__DIR__ . '/../' . $uploadedPath);
        $uploadedPath = null;
    }
}

// 4 Kartu Statistik Dinamis Sesuai Gambar 3 Jurnal
$total = (int) $pdo->query('SELECT COUNT(*) FROM gereja')->fetchColumn();
$totalKecamatan = (int) $pdo->query('SELECT COUNT(DISTINCT kecamatan) FROM gereja WHERE kecamatan IS NOT NULL AND TRIM(kecamatan) != ""')->fetchColumn();
if ($totalKecamatan === 0) {
    $totalKecamatan = count(allowedDistricts());
}
$totalCoords = (int) $pdo->query('SELECT COUNT(*) FROM gereja WHERE latitude IS NOT NULL AND longitude IS NOT NULL')->fetchColumn();
$totalPhotos = (int) $pdo->query("SELECT COUNT(*) FROM gereja WHERE foto IS NOT NULL AND TRIM(foto) != ''")->fetchColumn();

// Ambil seluruh daftar gereja untuk tabel
$churches = $pdo->query('SELECT id_gereja, nama_gereja, alamat, kecamatan, kabupaten, latitude, longitude, foto FROM gereja ORDER BY id_gereja ASC')->fetchAll();

renderAdminHeader('Pengelolaan Data Gereja', 'gereja');
?>

<!-- Header Pengelolaan Data Gereja (Gambar 3 Jurnal) -->
<div class="journal-header">
    <div class="journal-header-left">
        <h1 class="journal-title">Dashboard admin · <span class="journal-subtitle">Pengelolaan Data Gereja</span></h1>
        <p class="journal-subtitle">Daerah Sulut 2 · Wilayah 2 · Kab. Minahasa</p>
    </div>
    <div class="journal-header-actions">
        <a class="button btn-terracotta" href="index.php#form-gereja">+ Tambah Gereja</a>
        <form method="post" action="logout.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <button class="button secondary" type="submit">Logout</button>
        </form>
    </div>
</div>

<!-- Empat Kartu Ringkasan (Gambar 3 Jurnal) -->
<section class="stats-grid-journal" aria-label="Ringkasan data gereja">
    <article class="stat-card-journal">
        <span class="stat-label">Total gereja terdata</span>
        <strong class="stat-value"><?= $total ?></strong>
    </article>
    <article class="stat-card-journal">
        <span class="stat-label">Kecamatan</span>
        <strong class="stat-value"><?= $totalKecamatan ?></strong>
    </article>
    <article class="stat-card-journal">
        <span class="stat-label">Sudah berkoordinat</span>
        <strong class="stat-value"><?= $totalCoords ?> / <?= $total ?></strong>
    </article>
    <article class="stat-card-journal">
        <span class="stat-label">Sudah berfoto</span>
        <strong class="stat-value"><?= $totalPhotos ?> / <?= $total ?></strong>
    </article>
</section>

<!-- Notifikasi Error Validasi Form -->
<?php if ($errors !== []): ?>
    <div class="flash error" role="alert">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Layout Dua Kolom: Form (Kiri) & Peta (Kanan) -->
<div class="admin-two-col" id="form-gereja">
    <!-- Kolom Kiri: Form Data Gereja -->
    <div class="panel-card form-panel">
        <div class="panel-card-header">
            <h2 class="panel-card-title">Form Data Gereja</h2>
            <div class="form-tabs">
                <a class="tab-btn <?= !$isEdit ? 'active' : '' ?>" href="index.php#form-gereja">Tambah</a>
                <span class="tab-btn <?= $isEdit ? 'active' : 'disabled' ?>">Ubah</span>
            </div>
        </div>
        <div class="form-panel-body">
            <form method="post" enctype="multipart/form-data" action="index.php<?= $isEdit ? '?id=' . (int) $id : '' ?>#form-gereja">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="form_action" value="1">
                <div class="form-grid-journal">
                    <label class="wide">
                        Nama gereja <span class="required">*</span>
                        <input name="nama_gereja" maxlength="150" required value="<?= h($church['nama_gereja']) ?>" placeholder="Contoh: GSJA Alfa Omega Kakas">
                    </label>

                    <label>
                        Desa / Kelurahan
                        <input name="desa_kelurahan" maxlength="100" value="<?= h($desaValue) ?>" placeholder="Contoh: Sendangan">
                    </label>

                    <label>
                        Kecamatan <span class="required">*</span>
                        <select name="kecamatan" required>
                            <option value="">Pilih kecamatan</option>
                            <?php foreach (allowedDistricts() as $district): ?>
                                <option value="<?= h($district) ?>" <?= $church['kecamatan'] === $district ? 'selected' : '' ?>><?= h($district) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="wide">
                        Alamat/desa atau kelurahan <span class="required">*</span>
                        <textarea id="alamat" name="alamat" rows="2" maxlength="5000" required placeholder="Alamat lengkap gereja..."><?= h($church['alamat']) ?></textarea>
                    </label>

                    <div class="form-grid-three">
                        <label>
                            Kabupaten <span class="required">*</span>
                            <input name="kabupaten" maxlength="100" required value="<?= h($church['kabupaten']) ?>">
                        </label>
                        <label>
                            Daerah <span class="required">*</span>
                            <input name="daerah" maxlength="100" required value="<?= h($church['daerah']) ?>">
                        </label>
                        <label>
                            Wilayah <span class="required">*</span>
                            <input name="wilayah" maxlength="100" required value="<?= h($church['wilayah']) ?>">
                        </label>
                    </div>

                    <label>
                        Latitude
                        <input id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="<?= h($church['latitude']) ?>" placeholder="Contoh: 1.173857">
                    </label>

                    <label>
                        Longitude
                        <input id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="<?= h($church['longitude']) ?>" placeholder="Contoh: 124.885146">
                    </label>

                    <div class="wide">
                        <label>Foto gereja</label>
                        <div class="photo-upload-container">
                            <div class="photo-preview-box">
                                <?php if ($photo = safePhotoPath($church['foto'] ?? null)): ?>
                                    <img src="../<?= h($photo) ?>" alt="Foto gereja">
                                <?php else: ?>
                                    <span style="font-size: 1.6rem; color: #94a3b8;" aria-hidden="true">⛪</span>
                                <?php endif; ?>
                            </div>
                            <div class="photo-meta-info">
                                <?php if (!empty($church['foto'])): ?>
                                    <strong><?= h(basename((string) $church['foto'])) ?></strong>
                                <?php endif; ?>
                                <span>JPG, PNG, atau WEBP (maksimal 4 MB)</span>
                                <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions-journal">
                    <button class="button btn-terracotta" type="submit" aria-label="Simpan data">
                        <?= $isEdit ? 'Simpan perubahan' : 'Simpan data' ?>
                    </button>
                    <?php if ($isEdit): ?>
                        <a class="button secondary" href="index.php">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Kolom Kanan: Peta Penentuan Koordinat -->
    <div class="panel-card map-panel-admin">
        <div class="panel-card-header">
            <h2 class="panel-card-title">Penentuan Koordinat</h2>
            <span class="panel-card-sub">Ketik lat/long atau klik pada peta</span>
        </div>
        <div class="map-panel-body">
            <div class="map-container-relative">
                <div id="coordinate-map" role="region" aria-label="Peta pemilih titik koordinat Leaflet"></div>
                <div class="map-helper-badge">
                    Klik pada peta untuk menempatkan penanda. Nilai latitude & longitude pada form terisi otomatis.
                </div>
            </div>

            <div class="map-bottom-controls">
                <div class="map-coord-input-group">
                    <label for="map-lat-input">Latitude</label>
                    <input type="number" step="any" id="map-lat-input" placeholder="Latitude" value="<?= h($church['latitude']) ?>">
                </div>
                <div class="map-coord-input-group">
                    <label for="map-lng-input">Longitude</label>
                    <input type="number" step="any" id="map-lng-input" placeholder="Longitude" value="<?= h($church['longitude']) ?>">
                </div>
                <button type="button" id="btn-apply-coords" class="button secondary btn-apply-coords">Terapkan ke form</button>
            </div>
        </div>
    </div>
</div>

<!-- Panel Bawah: Tabel Daftar Gereja GSJA (Gambar 3 Jurnal) -->
<section class="table-panel">
    <div class="table-panel-header">
        <h2>Daftar Gereja GSJA</h2>
        <span class="data-badge"><?= $total ?> data</span>
    </div>

    <div class="table-toolbar">
        <div class="table-search-box">
            <input id="searchTableGereja" type="text" placeholder="Cari nama gereja..." autocomplete="off">
        </div>
        <div class="table-district-select">
            <select id="filterTableKecamatan">
                <option value="">Kecamatan: Semua ▼</option>
                <?php foreach (allowedDistricts() as $district): ?>
                    <option value="<?= h($district) ?>"><?= h($district) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($churches === []): ?>
        <p class="empty-state">Belum ada data gereja tersimpan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-no">NO</th>
                        <th>NAMA GEREJA</th>
                        <th>DESA/KELURAHAN</th>
                        <th>KECAMATAN</th>
                        <th>LATITUDE</th>
                        <th>LONGITUDE</th>
                        <th class="col-aksi">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($churches as $index => $row): ?>
                        <?php
                            $rowId = (int) $row['id_gereja'];
                            $isSelected = $isEdit && (int) $id === $rowId;
                            $desa = extractDesa((string) $row['alamat']);
                            $photoPath = safePhotoPath($row['foto'] ?? null);
                        ?>
                        <tr class="<?= $isSelected ? 'row-selected' : '' ?>">
                            <td class="col-no" data-label="No"><?= $index + 1 ?></td>
                            <td class="col-nama" data-label="Nama Gereja">
                                <strong><?= h($row['nama_gereja']) ?></strong>
                            </td>
                            <td data-label="Desa/Kelurahan"><?= h($desa) ?></td>
                            <td class="col-kecamatan" data-label="Kecamatan"><?= h($row['kecamatan']) ?></td>
                            <td data-label="Latitude"><?= $row['latitude'] !== null ? number_format((float) $row['latitude'], 6, '.', '') : '—' ?></td>
                            <td data-label="Longitude"><?= $row['longitude'] !== null ? number_format((float) $row['longitude'], 6, '.', '') : '—' ?></td>
                            <td class="col-aksi" data-label="Aksi">
                                <a class="btn-action-ubah" href="index.php?id=<?= $rowId ?>#form-gereja" aria-label="Edit">Ubah</a>
                                <form method="post" action="gereja_hapus.php" class="inline-form" onsubmit="return confirm('Hapus data gereja ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="id_gereja" value="<?= $rowId ?>">
                                    <button class="btn-action-hapus" type="submit">Hapus</button>
                                </form>
                                <?php if ($photoPath !== null): ?>
                                    <a class="badge-foto" href="../<?= h($photoPath) ?>" target="_blank" rel="noopener">Lihat foto</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-pagination-footer">
            <span>Menampilkan 1-<?= count($churches) ?> dari <?= $total ?> data</span>
            <span class="pagination-ctrl">&lt; 1 &gt;</span>
        </div>
    <?php endif; ?>
</section>

<!-- Script Leaflet & Integrasi Peta Koordinat -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="../assets/js/admin-map.js"></script>

<?php renderAdminFooter(); ?>
