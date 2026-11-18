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

// 4 Statistik Dinamis Aktual dari Database
$total = (int) $pdo->query('SELECT COUNT(*) FROM gereja')->fetchColumn();
$totalKecamatan = (int) $pdo->query('SELECT COUNT(DISTINCT kecamatan) FROM gereja WHERE kecamatan IS NOT NULL AND TRIM(kecamatan) != ""')->fetchColumn();
if ($totalKecamatan === 0) {
    $totalKecamatan = count(allowedDistricts());
}
$totalCoords = (int) $pdo->query('SELECT COUNT(*) FROM gereja WHERE latitude IS NOT NULL AND longitude IS NOT NULL')->fetchColumn();
$totalPhotos = (int) $pdo->query("SELECT COUNT(*) FROM gereja WHERE foto IS NOT NULL AND TRIM(foto) != ''")->fetchColumn();

// Distribusi per kecamatan aktual
$districtCounts = array_fill_keys(allowedDistricts(), 0);
$grouped = $pdo->query('SELECT kecamatan, COUNT(*) AS jumlah FROM gereja GROUP BY kecamatan')->fetchAll();
foreach ($grouped as $row) {
    if (isset($districtCounts[$row['kecamatan']])) {
        $districtCounts[$row['kecamatan']] = (int) $row['jumlah'];
    }
}

// 5 data gereja yang baru ditambahkan atau diperbarui
$recentChurches = $pdo->query('SELECT id_gereja, nama_gereja, alamat, kecamatan, latitude, longitude, foto, updated_at FROM gereja ORDER BY updated_at DESC LIMIT 5')->fetchAll();

renderAdminHeader('Dashboard Utama', 'dashboard');
?>

<!-- Header Dashboard -->
<div class="journal-header">
    <div class="journal-header-left">
        <h1 class="journal-title">Dashboard admin · <span class="journal-subtitle">Ringkasan Sistem WebGIS GSJA</span></h1>
        <p class="journal-subtitle">Wilayah 2 · Daerah Sulawesi Utara 2 · Kabupaten Minahasa</p>
    </div>
    <div class="journal-header-actions">
        <a class="button btn-terracotta" href="index.php">+ Kelola Data Gereja</a>
        <form method="post" action="logout.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <button class="button secondary" type="submit">Logout</button>
        </form>
    </div>
</div>

<!-- Empat Kartu Ringkasan Dinamis (Data Aktual Database) -->
<section class="stats-grid-journal" aria-label="Ringkasan data gereja aktual">
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

<!-- Grid Dua Kolom: Distribusi Sebaran & Kelengkapan Spasial -->
<div class="admin-two-col">
    <!-- Kolom Kiri: Distribusi Sebaran per Kecamatan -->
    <div class="panel-card">
        <div class="panel-card-header">
            <h2 class="panel-card-title">Sebaran Gereja per Kecamatan</h2>
            <span class="panel-card-sub">Data Wilayah 2 Kabupaten Minahasa</span>
        </div>
        <div class="dashboard-widget-body">
            <ul class="district-stat-list">
                <?php foreach ($districtCounts as $districtName => $churchCount): ?>
                    <?php
                        $percentage = $total > 0 ? round(($churchCount / $total) * 100) : 0;
                    ?>
                    <li class="district-stat-item">
                        <div class="district-meta">
                            <strong><?= h($districtName) ?></strong>
                            <span><?= $churchCount ?> gereja (<?= $percentage ?>%)</span>
                        </div>
                        <div class="district-progress-bar">
                            <div class="district-progress-fill" style="width: <?= $percentage ?>%;"></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Kolom Kanan: Status Kelengkapan & Aksi Cepat -->
    <div class="panel-card">
        <div class="panel-card-header">
            <h2 class="panel-card-title">Kelengkapan Data & Aksi Cepat</h2>
            <span class="panel-card-sub">Integrasi Spasial & Multimedia</span>
        </div>
        <div class="dashboard-widget-body">
            <div class="quick-status-grid">
                <div class="quick-status-box">
                    <span class="quick-status-title">Kelengkapan Koordinat</span>
                    <strong class="quick-status-num"><?= $total > 0 ? round(($totalCoords / $total) * 100) : 0 ?>%</strong>
                    <p class="quick-status-desc"><?= $total - $totalCoords ?> gereja belum memiliki titik koordinat.</p>
                    <a class="button small secondary" href="koordinat.php">Buka Penentuan Koordinat →</a>
                </div>
                <div class="quick-status-box">
                    <span class="quick-status-title">Kelengkapan Foto</span>
                    <strong class="quick-status-num"><?= $total > 0 ? round(($totalPhotos / $total) * 100) : 0 ?>%</strong>
                    <p class="quick-status-desc"><?= $total - $totalPhotos ?> gereja belum memiliki dokumentasi foto.</p>
                    <a class="button small secondary" href="foto.php">Buka Galeri Foto →</a>
                </div>
            </div>
            <div class="dashboard-banner-note">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                <span>Gunakan menu navigasi sidebar untuk mengelola data, memetakan koordinat, dan memperbarui foto gereja.</span>
            </div>
        </div>
    </div>
</div>

<!-- Panel Bawah: 5 Data Gereja Terbaru / Terakhir Diperbarui -->
<section class="table-panel">
    <div class="table-panel-header">
        <h2>Data Gereja Terbaru / Terakhir Diperbarui</h2>
        <a class="button small secondary" href="index.php">Lihat Seluruh Data (<?= $total ?>) →</a>
    </div>

    <?php if ($recentChurches === []): ?>
        <p class="empty-state">Belum ada data gereja tersimpan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>NAMA GEREJA</th>
                        <th>KECAMATAN</th>
                        <th>STATUS KOORDINAT</th>
                        <th>STATUS FOTO</th>
                        <th>TERAKHIR DIPERBARUI</th>
                        <th class="col-aksi">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentChurches as $row): ?>
                        <?php
                            $hasCoord = $row['latitude'] !== null && $row['longitude'] !== null;
                            $hasPhoto = safePhotoPath($row['foto'] ?? null) !== null;
                            $updatedTime = !empty($row['updated_at']) ? date('d M Y, H:i', strtotime((string)$row['updated_at'])) : '—';
                        ?>
                        <tr>
                            <td class="col-nama">
                                <strong><?= h($row['nama_gereja']) ?></strong>
                            </td>
                            <td><?= h($row['kecamatan']) ?></td>
                            <td>
                                <?php if ($hasCoord): ?>
                                    <span class="badge-status success">✓ Berkoordinat</span>
                                <?php else: ?>
                                    <span class="badge-status warning">Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasPhoto): ?>
                                    <span class="badge-status success">✓ Berfoto</span>
                                <?php else: ?>
                                    <span class="badge-status warning">Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #64748b; font-size: 0.82rem;"><?= h($updatedTime) ?></td>
                            <td class="col-aksi">
                                <a class="btn-action-ubah" href="index.php?id=<?= (int)$row['id_gereja'] ?>#form-gereja">Kelola Data</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php renderAdminFooter(); ?>
