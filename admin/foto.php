<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
requireAdmin();

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $exception) {
    error_log('Halaman foto gagal memuat database: ' . $exception->getMessage());
    http_response_code(500);
    exit('Halaman pengelolaan foto belum dapat dimuat.');
}

$errors = [];

/** Hapus berkas foto sebelum membersihkan referensi database, dengan salinan pemulihan sementara. */
function deletePhotoFileThenClearReference(?string $photoFile, callable $clearReference, ?callable $unlinkFile = null): array
{
    $unlinkFile ??= static fn(string $path): bool => unlink($path);
    $fileExists = $photoFile !== null && is_file($photoFile);

    if (!$fileExists) {
        $clearReference();
        return ['file_found' => false, 'backup_cleanup_failed' => false];
    }

    $backupFile = dirname($photoFile) . DIRECTORY_SEPARATOR . '.' . basename($photoFile)
        . '.restore-' . bin2hex(random_bytes(16));
    if (!@copy($photoFile, $backupFile)) {
        throw new RuntimeException('Tidak dapat menyiapkan pemulihan foto sebelum menghapusnya.');
    }

    if (!@$unlinkFile($photoFile)) {
        if (is_file($backupFile) && !@unlink($backupFile)) {
            error_log('Gagal membersihkan salinan pemulihan foto: ' . $backupFile);
        }
        throw new RuntimeException('Berkas foto tidak dapat dihapus. Referensi database tidak diubah.');
    }

    try {
        $clearReference();
    } catch (Throwable $exception) {
        if (!@rename($backupFile, $photoFile)) {
            error_log('Gagal memulihkan foto setelah pembaruan database gagal. Salinan pemulihan: ' . $backupFile);
            throw new RuntimeException('Pembaruan database gagal dan foto tidak dapat dipulihkan otomatis.', 0, $exception);
        }
        throw $exception;
    }

    if (is_file($backupFile) && !@unlink($backupFile)) {
        error_log('Referensi foto telah dihapus, tetapi salinan pemulihan gagal dibersihkan: ' . $backupFile);
        return ['file_found' => true, 'backup_cleanup_failed' => true];
    }

    return ['file_found' => true, 'backup_cleanup_failed' => false];
}

// Proses POST: Unggah / Ganti Foto atau Hapus Foto
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? '';
    $idGereja = filter_input(INPUT_POST, 'id_gereja', FILTER_VALIDATE_INT);

    if ($idGereja === false || $idGereja === null || $idGereja < 1) {
        $errors[] = 'Gereja yang dipilih tidak valid.';
    } else {
        try {
            $stmtCheck = $pdo->prepare('SELECT nama_gereja, foto FROM gereja WHERE id_gereja = :id');
            $stmtCheck->execute(['id' => $idGereja]);
            $currentChurch = $stmtCheck->fetch();

            if (!$currentChurch) {
                $errors[] = 'Data gereja tidak ditemukan.';
            } elseif ($action === 'delete_photo') {
                $oldPhoto = safePhotoPath($currentChurch['foto'] ?? null);
                $oldFile = $oldPhoto !== null ? __DIR__ . '/../' . $oldPhoto : null;
                $deleteResult = deletePhotoFileThenClearReference(
                    $oldFile,
                    static function () use ($pdo, $idGereja): void {
                        $stmtDel = $pdo->prepare('UPDATE gereja SET foto = NULL WHERE id_gereja = :id');
                        $stmtDel->execute(['id' => $idGereja]);
                    }
                );

                if ($deleteResult['backup_cleanup_failed']) {
                    $errors[] = 'Referensi foto sudah dihapus, tetapi salinan pemulihan sementara gagal dibersihkan. Periksa log server.';
                } else {
                    $message = $deleteResult['file_found']
                        ? 'Foto untuk ' . (string) $currentChurch['nama_gereja'] . ' berhasil dihapus.'
                        : 'Berkas foto untuk ' . (string) $currentChurch['nama_gereja'] . ' tidak ditemukan; referensi database telah dibersihkan.';
                    setFlash('success', $message);
                    header('Location: foto.php');
                    exit;
                }

            } elseif ($action === 'upload_photo') {
                // Unggah foto baru atau ganti foto lama
                $file = $_FILES['foto'] ?? null;
                $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                if (!is_array($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
                    $errors[] = 'Pilih berkas foto terlebih dahulu.';
                } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                    $errors[] = 'Berkas foto gagal diunggah.';
                } elseif ($file['size'] < 1 || $file['size'] > 4 * 1024 * 1024) {
                    $errors[] = 'Ukuran berkas foto maksimal 4 MB.';
                } else {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                    $imageInfo = @getimagesize($file['tmp_name']);

                    if (!isset($mimeExtensions[$mime]) || !$imageInfo || ($imageInfo['mime'] ?? '') !== $mime) {
                        $errors[] = 'Format berkas foto harus JPG, JPEG, PNG, atau WEBP yang valid.';
                    } else {
                        $filename = bin2hex(random_bytes(16)) . '.' . $mimeExtensions[$mime];
                        $destination = __DIR__ . '/../uploads/gereja/' . $filename;

                        if (!move_uploaded_file($file['tmp_name'], $destination)) {
                            $errors[] = 'Berkas foto tidak dapat disimpan di server.';
                        } else {
                            $newPhotoPath = 'uploads/gereja/' . $filename;
                            $oldPhoto = safePhotoPath($currentChurch['foto'] ?? null);

                            $stmtUp = $pdo->prepare('UPDATE gereja SET foto = :foto WHERE id_gereja = :id');
                            $stmtUp->execute([
                                'foto' => $newPhotoPath,
                                'id' => $idGereja,
                            ]);

                            if ($oldPhoto !== null) {
                                $oldFile = __DIR__ . '/../' . $oldPhoto;
                                if (is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }

                            setFlash('success', 'Foto gereja ' . (string) $currentChurch['nama_gereja'] . ' berhasil diperbarui.');
                            header('Location: foto.php');
                            exit;
                        }
                    }
                }
            }
        } catch (Throwable $exception) {
            error_log('Gagal mengelola foto gereja: ' . $exception->getMessage());
            $errors[] = 'Terjadi kesalahan sistem saat memproses foto.';
        }
    }
}

// Ambil seluruh daftar gereja
$churches = $pdo->query('SELECT id_gereja, nama_gereja, alamat, kecamatan, foto FROM gereja ORDER BY nama_gereja ASC')->fetchAll();

$totalCount = count($churches);
$withPhotoCount = 0;
foreach ($churches as $c) {
    if (safePhotoPath($c['foto'] ?? null) !== null) {
        $withPhotoCount++;
    }
}
$withoutPhotoCount = $totalCount - $withPhotoCount;

renderAdminHeader('Foto Gereja', 'foto');
?>

<!-- Header Pengelolaan Foto -->
<div class="journal-header">
    <div class="journal-header-left">
        <h1 class="journal-title">Pengelolaan Foto Gereja</h1>
        <p class="journal-subtitle">Dokumentasi Fasad & Bangunan Gereja GSJA Wilayah 2</p>
    </div>
    <div class="journal-header-actions">
        <a class="button secondary" href="index.php">← Kelola Data Gereja</a>
        <a class="button btn-terracotta" href="../index.php" target="_blank" rel="noopener">Peta Publik ↗</a>
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

<!-- 3 Kartu Ringkasan Status Foto -->
<section class="stats-grid-journal" style="grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: 1.25rem;">
    <article class="stat-card-journal">
        <span class="stat-label">Total Gereja</span>
        <strong class="stat-value"><?= $totalCount ?></strong>
    </article>
    <article class="stat-card-journal">
        <span class="stat-label">Foto Tersedia</span>
        <strong class="stat-value" style="color: #16a34a;"><?= $withPhotoCount ?> / <?= $totalCount ?></strong>
    </article>
    <article class="stat-card-journal">
        <span class="stat-label">Belum Ada Foto</span>
        <strong class="stat-value" style="color: #ea580c;"><?= $withoutPhotoCount ?></strong>
    </article>
</section>

<!-- Filter Galeri Foto -->
<div class="table-panel" style="margin-bottom: 1.5rem; padding: 0.85rem 1.25rem;">
    <div class="table-toolbar" style="margin-bottom: 0;">
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <button type="button" class="tab-btn active filter-photo-tab" data-filter="all">Semua Gereja (<?= $totalCount ?>)</button>
            <button type="button" class="tab-btn filter-photo-tab" data-filter="has-photo">Ada Foto (<?= $withPhotoCount ?>)</button>
            <button type="button" class="tab-btn filter-photo-tab" data-filter="no-photo">Belum Ada Foto (<?= $withoutPhotoCount ?>)</button>
        </div>
        <div class="table-district-select">
            <select id="filterPhotoDistrict">
                <option value="">Kecamatan: Semua ▼</option>
                <?php foreach (allowedDistricts() as $district): ?>
                    <option value="<?= h($district) ?>"><?= h($district) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- Galeri Kartu Foto Gereja -->
<div class="photo-gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 1.25rem;">
    <?php foreach ($churches as $row): ?>
        <?php
            $photoPath = safePhotoPath($row['foto'] ?? null);
            $hasPhoto = $photoPath !== null;
            $rowId = (int) $row['id_gereja'];
        ?>
        <div class="panel-card photo-card-item"
             data-has-photo="<?= $hasPhoto ? 'true' : 'false' ?>"
             data-district="<?= h($row['kecamatan']) ?>">
            <!-- Thumbnail Box -->
            <div style="height: 180px; width: 100%; background: #0f172a; position: relative; overflow: hidden; display: grid; place-items: center;">
                <?php if ($hasPhoto): ?>
                    <img src="../<?= h($photoPath) ?>" alt="Foto <?= h($row['nama_gereja']) ?>"
                         style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.2s ease;">
                    <a href="../<?= h($photoPath) ?>" target="_blank" rel="noopener"
                       style="position: absolute; bottom: 8px; right: 8px; background: rgba(15, 23, 42, 0.85); color: #ffffff; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.75rem; text-decoration: none; font-weight: 500;">
                        🔍 Lihat Penuh
                    </a>
                <?php else: ?>
                    <div style="text-align: center; color: #94a3b8;">
                        <span style="font-size: 2.5rem; display: block; margin-bottom: 0.25rem;">⛪</span>
                        <span style="font-size: 0.82rem;">Dokumentasi foto belum diunggah</span>
                    </div>
                <?php endif; ?>

                <span style="position: absolute; top: 8px; left: 8px; font-size: 0.72rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 4px; <?= $hasPhoto ? 'background: #22c55e; color: #ffffff;' : 'background: #f97316; color: #ffffff;' ?>">
                    <?= $hasPhoto ? '✓ Foto Tersedia' : 'Belum Ada Foto' ?>
                </span>
            </div>

            <!-- Card Body -->
            <div style="padding: 1rem;">
                <h3 style="margin: 0 0 0.35rem; font-size: 1rem; font-weight: 700; color: #0f172a;">
                    <?= h($row['nama_gereja']) ?>
                </h3>
                <p style="margin: 0 0 0.85rem; font-size: 0.82rem; color: #64748b;">
                    Kecamatan <?= h($row['kecamatan']) ?> · <?= h($row['alamat']) ?>
                </p>

                <!-- Form Upload / Ganti Foto Cepat -->
                <form method="post" enctype="multipart/form-data" action="foto.php" style="margin-bottom: 0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="hidden" name="id_gereja" value="<?= $rowId ?>">
                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                        <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required
                               style="font-size: 0.75rem; padding: 0.3rem; min-height: 32px;">
                        <button type="submit" class="button small btn-terracotta" style="white-space: nowrap;">
                            <?= $hasPhoto ? 'Ganti' : 'Unggah' ?>
                        </button>
                    </div>
                </form>

                <?php if ($hasPhoto): ?>
                    <form method="post" action="foto.php" onsubmit="return confirm('Hapus foto gereja <?= h(addslashes($row['nama_gereja'])) ?>?')">
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete_photo">
                        <input type="hidden" name="id_gereja" value="<?= $rowId ?>">
                        <button type="submit" class="button small danger" style="width: 100%; margin-top: 0.25rem;">
                            🗑 Hapus Foto
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    const tabs = document.querySelectorAll('.filter-photo-tab');
    const districtSelect = document.getElementById('filterPhotoDistrict');
    const cards = document.querySelectorAll('.photo-card-item');

    let currentFilter = 'all';

    function applyFilters() {
        const district = districtSelect ? districtSelect.value.trim() : '';

        cards.forEach(card => {
            const hasPhoto = card.getAttribute('data-has-photo') === 'true';
            const cardDistrict = card.getAttribute('data-district') || '';

            let matchesTab = true;
            if (currentFilter === 'has-photo') matchesTab = hasPhoto;
            if (currentFilter === 'no-photo') matchesTab = !hasPhoto;

            let matchesDistrict = true;
            if (district !== '') matchesDistrict = cardDistrict === district;

            card.style.display = (matchesTab && matchesDistrict) ? '' : 'none';
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentFilter = tab.getAttribute('data-filter') || 'all';
            applyFilters();
        });
    });

    if (districtSelect) {
        districtSelect.addEventListener('change', applyFilters);
    }
})();
</script>

<?php renderAdminFooter(); ?>
