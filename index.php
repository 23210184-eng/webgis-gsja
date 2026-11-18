<?php
declare(strict_types=1);

$pageTitle = 'WebGIS Pemetaan Gereja GSJA · Kabupaten Minahasa';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="WebGIS Pemetaan Gereja GSJA Daerah Sulut 2 Wilayah 2 Kabupaten Minahasa">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="public-body">

<!-- Header Navy Gelap Sesuai Gambar 2 Jurnal -->
<header class="site-header">
    <div class="header-inner">
        <div class="brand">
            <div class="brand-badge" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M11 2h2v5h5v2h-5v13h-2V9H6V7h5V2z"/>
                </svg>
            </div>
            <div class="brand-text">
                <h1 class="brand-title">WebGIS Pemetaan Gereja GSJA</h1>
                <p class="brand-subtitle">Persebaran Gereja · Daerah Sulut 2 · Wilayah 2 · Kab. Minahasa</p>
            </div>
        </div>

        <nav class="header-nav" aria-label="Navigasi Utama">
            <button type="button" class="nav-item active" id="nav-btn-map" title="Tampilkan peta sebaran">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                    <line x1="8" y1="2" x2="8" y2="18"/>
                    <line x1="16" y1="6" x2="16" y2="22"/>
                </svg>
                <span>Peta Sebaran</span>
            </button>
            <button type="button" class="nav-item" id="nav-btn-list" title="Buka daftar gereja">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
                <span>Daftar Gereja</span>
            </button>
            <button type="button" class="nav-item" id="nav-btn-about" title="Tentang WebGIS GSJA">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                <span>Tentang</span>
            </button>
            <a class="nav-item btn-login-nav" href="admin/login.php" title="Masuk ke Panel Admin">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                <span>Login Admin</span>
            </a>
        </nav>
    </div>
</header>

<!-- Main Container: Sidebar Kiri & Area Peta -->
<div class="webgis-layout">
    <!-- Panel Kiri: Pencarian, Filter & Daftar Gereja (Gambar 2 Jurnal) -->
    <aside class="sidebar-panel" id="sidebar-panel" aria-label="Panel Pencarian dan Daftar Gereja">
        <div class="sidebar-header">
            <div class="sidebar-header-title">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <h2>Cari & Filter Gereja</h2>
            </div>
            <button type="button" class="btn-close-sidebar" id="btn-close-sidebar" aria-label="Tutup panel samping" title="Tutup">
                &times;
            </button>
        </div>

        <div class="sidebar-controls">
            <!-- Form Pencarian -->
            <div class="search-field-group">
                <label class="sr-only" for="search-gereja">Cari gereja</label>
                <div class="search-input-box">
                    <svg class="search-icon-svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input id="search-gereja" type="search" placeholder="Cari nama gereja..." autocomplete="off" aria-label="Cari gereja">
                    <button type="button" id="btn-clear-search" class="btn-clear-search" aria-label="Hapus kata kunci pencarian" hidden>&times;</button>
                </div>
                <!-- Dropdown hasil pencarian (mendukung tes Playwright listbox option) -->
                <div id="search-results" class="search-results" role="listbox" aria-label="Hasil pencarian" hidden></div>
            </div>

            <!-- Filter Kecamatan (Dropdown) -->
            <div class="filter-field-group">
                <label for="filter-kecamatan" class="filter-label">
                    <span>Kecamatan:</span>
                </label>
                <select id="filter-kecamatan" aria-label="Filter kecamatan">
                    <option value="">Semua kecamatan</option>
                    <option value="Kakas">Kakas</option>
                    <option value="Eris">Eris</option>
                    <option value="Tondano Barat">Tondano Barat</option>
                    <option value="Tondano Timur">Tondano Timur</option>
                    <option value="Tondano Utara">Tondano Utara</option>
                </select>
            </div>

            <!-- Filter Cepat (Pill Buttons sesuai Gambar 2 Jurnal) -->
            <div class="district-pills" id="district-pills" role="group" aria-label="Pilihan cepat kecamatan">
                <button type="button" class="pill-btn active" data-kecamatan="">Semua</button>
                <button type="button" class="pill-btn" data-kecamatan="Kakas">Kakas: <span class="pill-count" id="pill-count-kakas">0</span></button>
                <button type="button" class="pill-btn" data-kecamatan="Eris">Eris: <span class="pill-count" id="pill-count-eris">0</span></button>
                <button type="button" class="pill-btn" data-kecamatan="Tondano Barat">Tondano Barat: <span class="pill-count" id="pill-count-tondano-barat">0</span></button>
                <button type="button" class="pill-btn" data-kecamatan="Tondano Timur">Tondano Timur: <span class="pill-count" id="pill-count-tondano-timur">0</span></button>
                <button type="button" class="pill-btn" data-kecamatan="Tondano Utara">Tondano Utara: <span class="pill-count" id="pill-count-tondano-utara">0</span></button>
            </div>

            <!-- Ringkasan Jumlah Data Sesuai Gambar 2 -->
            <div class="count-summary-bar">
                <span class="count-summary-text">
                    Menampilkan <strong id="church-count">—</strong> dari <span id="church-total">—</span> gereja
                </span>
                <span class="region-badge">Kab. Minahasa</span>
                <span id="count-label" class="sr-only">memuat data gereja</span>
            </div>

            <p id="map-status" class="map-status" role="status" aria-live="polite">Memuat data gereja...</p>
        </div>

        <!-- Daftar Gereja Interaktif (Scrollable List) -->
        <div class="church-list-container" id="church-list-container">
            <div id="church-list" class="church-list" role="list" aria-label="Daftar gereja GSJA">
                <!-- Diisi otomatis oleh JavaScript -->
            </div>
            <div id="church-list-empty" class="church-list-empty" hidden>
                <div class="empty-icon-wrap" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        <line x1="8" y1="11" x2="14" y2="11"/>
                    </svg>
                </div>
                <h3>Gereja tidak ditemukan</h3>
                <p id="empty-state-message">Tidak ada data gereja yang sesuai dengan kriteria pencarian atau filter kecamatan saat ini.</p>
                <button type="button" class="btn-reset-filters" id="btn-reset-filters">Tampilkan Semua Gereja</button>
            </div>
        </div>
    </aside>

    <!-- Area Utama Peta Leaflet & Kartu Informasi -->
    <main class="map-area" aria-label="Area Peta WebGIS GSJA">
        <!-- Leaflet Container -->
        <div id="map" role="region" aria-label="Peta persebaran gereja GSJA OpenStreetMap"></div>

        <!-- Kartu Judul Peta di Kanan Atas (Gambar 2 Jurnal) -->
        <div class="map-card-title" role="region" aria-label="Judul Peta">
            <h2 class="card-title-main">Sebaran Gereja GSJA</h2>
            <p class="card-title-sub">Kabupaten Minahasa, Sulawesi Utara</p>
        </div>

        <!-- Legenda Peta di Kiri Bawah (Gambar 2 Jurnal) -->
        <div class="map-card-legend" role="region" aria-label="Legenda Simbol Peta">
            <div class="legend-header">
                <strong>Keterangan</strong>
            </div>
            <div class="legend-items">
                <div class="legend-item">
                    <span class="legend-icon-marker" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="#e11d48">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>
                        </svg>
                    </span>
                    <span class="legend-label">Lokasi gereja GSJA (<span id="legend-church-count">14</span>)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color-box lake-box" aria-hidden="true"></span>
                    <span class="legend-label">Danau Tondano</span>
                </div>
                <div class="legend-item">
                    <span class="legend-line-box boundary-box" aria-hidden="true"></span>
                    <span class="legend-label">Batas kecamatan</span>
                </div>
            </div>
        </div>

        <!-- Tombol Buka Panel di Mobile -->
        <button type="button" class="btn-mobile-sidebar" id="btn-mobile-sidebar" aria-label="Buka panel pencarian dan daftar gereja">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <span>Daftar Gereja (<span id="mobile-badge-count">14</span>)</span>
        </button>
    </main>
</div>

<!-- Modal Informasi "Tentang WebGIS GSJA" -->
<div class="modal-backdrop" id="about-modal" role="dialog" aria-modal="true" aria-labelledby="about-modal-title" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <div class="modal-badge-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M11 2h2v5h5v2h-5v13h-2V9H6V7h5V2z"/>
                    </svg>
                </div>
                <h3 id="about-modal-title">Tentang WebGIS GSJA Minahasa</h3>
            </div>
            <button type="button" class="btn-modal-close" id="btn-close-about" aria-label="Tutup dialog tentang">&times;</button>
        </div>
        <div class="modal-content">
            <p><strong>WebGIS Pemetaan Gereja GSJA</strong> adalah sistem informasi geografis berbasis web untuk memetakan persebaran gereja-gereja Sidang Jemaat Allah (GSJA) di <strong>Kabupaten Minahasa, Sulawesi Utara</strong>, khususnya pada <strong>Wilayah 2 Daerah Sulut 2</strong>.</p>
            <p>Sistem ini dikembangkan mengacu pada penelitian:</p>
            <blockquote class="about-quote">
                <em>"Pengembangan WebGIS Pemetaan Gereja GSJA Studi Kasus: Sulut 2 Wilayah 2 Kabupaten Minahasa"</em>
            </blockquote>
            <h4 class="about-section-heading">Cakupan 5 Kecamatan Penelitian:</h4>
            <ul class="about-districts-list">
                <li><strong>Kecamatan Kakas:</strong> 7 Gereja</li>
                <li><strong>Kecamatan Eris:</strong> 1 Gereja</li>
                <li><strong>Kecamatan Tondano Barat:</strong> 2 Gereja</li>
                <li><strong>Kecamatan Tondano Timur:</strong> 1 Gereja</li>
                <li><strong>Kecamatan Tondano Utara:</strong> 3 Gereja</li>
            </ul>
            <p class="about-tech-note">Dibangun menggunakan teknologi PHP Native, MySQL, Leaflet.js 1.9.4, dan peta OpenStreetMap tanpa dependensi Google Maps API.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-primary" id="btn-confirm-about">Tutup</button>
        </div>
    </div>
</div>

<script src="assets/vendor/leaflet/leaflet.js"></script>
<script src="assets/js/map.js"></script>
</body>
</html>
