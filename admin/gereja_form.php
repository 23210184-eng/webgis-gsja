<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
requireAdmin();

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $exception) {
    error_log('Form data gereja gagal tersambung ke database: ' . $exception->getMessage());
    http_response_code(500);
    exit('Form belum dapat dimuat. Periksa koneksi database.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || ($id !== null && $id < 1)) {
    http_response_code(400);
    exit('ID data tidak valid.');
}
$isEdit = $id !== null;
$church = [
    'nama_gereja' => '', 'alamat' => '', 'kecamatan' => '',
    'kabupaten' => 'Minahasa', 'daerah' => 'Sulawesi Utara 2',
    'wilayah' => 'Wilayah 2', 'latitude' => '', 'longitude' => '', 'foto' => null,
];
$desaValue = '';
$errors = [];
$uploadedPath = null;

function extractDesaForm(string $alamat): string
{
    $alamat = trim($alamat);
    if ($alamat === '') return '—';
    $parts = explode(',', $alamat);
    $first = trim($parts[0]);
    $cleaned = preg_replace('/^(Desa|Kelurahan)\s+/i', '', $first);
    return $cleaned !== '' ? $cleaned : $first;
}

if ($isEdit) {
    try {
        $statement = $pdo->prepare('SELECT * FROM gereja WHERE id_gereja = :id');
        $statement->execute(['id' => $id]);
        $existing = $statement->fetch();
    } catch (Throwable $exception) {
        error_log('Gagal memuat data untuk edit gereja: ' . $exception->getMessage());
        http_response_code(500);
        exit('Data gereja belum dapat dimuat.');
    }
    if (!$existing) {
        http_response_code(404);
        exit('Data gereja tidak ditemukan.');
    }
    $church = $existing;
    $desaValue = extractDesaForm((string) $church['alamat']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrfToken();
    foreach (['nama_gereja', 'alamat', 'kecamatan', 'kabupaten', 'daerah', 'wilayah', 'latitude', 'longitude'] as $field) {
        $church[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $desaValue = trim((string) ($_POST['desa_kelurahan'] ?? ''));
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
            'nama_gereja' => $church['nama_gereja'], 'alamat' => $church['alamat'], 'kecamatan' => $church['kecamatan'],
            'kabupaten' => $church['kabupaten'], 'daerah' => $church['daerah'], 'wilayah' => $church['wilayah'],
            'latitude' => $latitude === null ? null : (float) $latitude,
            'longitude' => $longitude === null ? null : (float) $longitude,
            'foto' => $photo,
        ];
        try {
            if ($isEdit) {
                $values['id'] = $id;
                $statement = $pdo->prepare('UPDATE gereja SET nama_gereja = :nama_gereja, alamat = :alamat, kecamatan = :kecamatan, kabupaten = :kabupaten, daerah = :daerah, wilayah = :wilayah, latitude = :latitude, longitude = :longitude, foto = :foto WHERE id_gereja = :id');
                $statement->execute($values);
            } else {
                $statement = $pdo->prepare('INSERT INTO gereja (nama_gereja, alamat, kecamatan, kabupaten, daerah, wilayah, latitude, longitude, foto) VALUES (:nama_gereja, :alamat, :kecamatan, :kabupaten, :daerah, :wilayah, :latitude, :longitude, :foto)');
                $statement->execute($values);
            }
            if ($uploadedPath !== null && $oldPhoto !== null) {
                $oldFile = __DIR__ . '/../' . $oldPhoto;
                if (is_file($oldFile)) @unlink($oldFile);
            }
            setFlash('success', $isEdit ? 'Data gereja berhasil diperbarui.' : 'Data gereja berhasil ditambahkan.');
            header('Location: index.php');
            exit;
        } catch (Throwable $exception) {
            if ($uploadedPath !== null) @unlink(__DIR__ . '/../' . $uploadedPath);
            error_log('Simpan data gereja gagal: ' . $exception->getMessage());
            $errors[] = 'Data belum dapat disimpan. Periksa database dan log PHP.';
        }
    } elseif ($uploadedPath !== null) {
        @unlink(__DIR__ . '/../' . $uploadedPath);
        $uploadedPath = null;
    }
}

renderAdminHeader($isEdit ? 'Edit Gereja' : 'Tambah Gereja', 'dashboard');
?>

<div class="journal-header">
    <div class="journal-header-left">
        <h1 class="journal-title"><?= $isEdit ? 'Edit Data Gereja' : 'Tambah Data Gereja' ?></h1>
        <p class="journal-subtitle">Daerah Sulut 2 · Wilayah 2 · Kab. Minahasa</p>
    </div>
    <div class="journal-header-actions">
        <a class="button secondary" href="index.php">← Kembali ke Dashboard</a>
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

<div class="admin-two-col">
    <!-- Kolom Kiri: Form Data Gereja -->
    <div class="panel-card form-panel">
        <div class="panel-card-header">
            <h2 class="panel-card-title">Form Data Gereja</h2>
            <div class="form-tabs">
                <a class="tab-btn <?= !$isEdit ? 'active' : '' ?>" href="gereja_form.php">Tambah</a>
                <span class="tab-btn <?= $isEdit ? 'active' : 'disabled' ?>">Ubah</span>
            </div>
        </div>
        <div class="form-panel-body">
            <form method="post" enctype="multipart/form-data" action="gereja_form.php<?= $isEdit ? '?id=' . (int) $id : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
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
                        <label>Foto gereja (JPG, PNG, WEBP; maksimal 4 MB)</label>
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
                                <span>Pilih berkas baru untuk mengubah foto:</span>
                                <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions-journal">
                    <button class="button btn-terracotta" type="submit" aria-label="Simpan data">
                        Simpan data
                    </button>
                    <a class="button secondary" href="index.php">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Kolom Kanan: Penentuan Koordinat -->
    <div class="panel-card map-panel-admin">
        <div class="panel-card-header">
            <h2 class="panel-card-title">Penentuan Koordinat</h2>
            <span class="panel-card-sub">Ketik lat/long atau klik pada peta</span>
        </div>
        <div class="map-panel-body">
            <div class="map-container-relative">
                <div id="coordinate-map" role="region" aria-label="Peta untuk memilih koordinat gereja"></div>
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="../assets/js/admin-map.js"></script>

<?php renderAdminFooter(); ?>
