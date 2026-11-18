<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrfToken(), $submitted)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman lalu coba kembali.');
    }
}

function requireAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function renderAdminHeader(string $title, string $activeMenu = 'dashboard'): void
{
    $flash = takeFlash();
    $adminName = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    $initial = mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'), 'UTF-8');
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> · Admin WebGIS GSJA</title>
        <link rel="stylesheet" href="../assets/css/admin.css">
    </head>
    <body class="admin-body">
    <div class="admin-wrapper">
        <aside class="admin-sidebar" aria-label="Navigasi Panel Admin">
            <div class="sidebar-brand">
                <div class="brand-badge" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M10 2h4v6h6v4h-6v10h-4V12H4V8h6V2z"/>
                    </svg>
                </div>
                <div class="brand-meta">
                    <span class="brand-name">WebGIS GSJA</span>
                    <span class="brand-sub">Kabupaten Minahasa</span>
                </div>
            </div>

            <div class="sidebar-profile">
                <div class="profile-avatar" aria-hidden="true"><?= h($initial) ?></div>
                <div class="profile-meta">
                    <strong class="profile-name"><?= h($adminName) ?></strong>
                    <span class="profile-badge">Administrator</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <p class="nav-section-title">Menu Navigasi</p>
                <ul class="nav-list">
                    <li>
                        <a href="dashboard.php" class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect width="7" height="9" x="3" y="3" rx="1"/>
                                <rect width="7" height="5" x="14" y="3" rx="1"/>
                                <rect width="7" height="9" x="14" y="12" rx="1"/>
                                <rect width="7" height="5" x="3" y="16" rx="1"/>
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="index.php" class="nav-link <?= $activeMenu === 'gereja' ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M18 20V6a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v14"/>
                                <path d="M2 20h20"/>
                                <path d="M14 12v.01"/>
                                <path d="M12 2v2"/>
                                <path d="M10 3h4"/>
                            </svg>
                            <span>Kelola Data Gereja</span>
                        </a>
                    </li>
                    <li>
                        <a href="koordinat.php" class="nav-link <?= $activeMenu === 'koordinat' ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span>Penentuan Koordinat</span>
                        </a>
                    </li>
                    <li>
                        <a href="foto.php" class="nav-link <?= $activeMenu === 'foto' ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                <circle cx="9" cy="9" r="2"/>
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                            </svg>
                            <span>Foto Gereja</span>
                        </a>
                    </li>
                    <li>
                        <a href="../index.php" class="nav-link" target="_blank" rel="noopener">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/>
                                <path d="M2 12h20"/>
                            </svg>
                            <span>Lihat Peta Publik</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user-footer">
                    <strong>Admin Wilayah 2</strong>
                    <span>Daerah Sulut 2</span>
                </div>
                <form method="post" action="logout.php" class="sidebar-logout-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <button type="submit" class="sidebar-logout-btn">
                        <span>› Keluar [Logout]</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-main-area">
            <header class="admin-topbar">
                <div class="topbar-left">
                    <span class="topbar-badge">Panel Pengelolaan Data</span>
                    <span class="topbar-title"><?= h($title) ?></span>
                </div>
                <div class="topbar-right">
                    <a class="topbar-link" href="../index.php" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>
                        <span>Peta Publik</span>
                    </a>
                </div>
            </header>

            <main class="admin-content">
                <?php if ($flash !== null): ?>
                    <p class="flash <?= h($flash['type'] ?? 'info') ?>" role="status"><?= h($flash['message'] ?? '') ?></p>
                <?php endif; ?>
    <?php
}

function renderAdminFooter(): void
{
    ?>
            </main>
            <footer class="admin-footer">
                <p>&copy; <?= date('Y') ?> WebGIS Pemetaan Gereja GSJA Wilayah 2 Kabupaten Minahasa</p>
            </footer>
        </div>
    </div>
    </body>
    </html>
    <?php
}

function allowedDistricts(): array
{
    return ['Kakas', 'Eris', 'Tondano Barat', 'Tondano Timur', 'Tondano Utara'];
}

function safePhotoPath(?string $path): ?string
{
    if ($path === null || !preg_match('~^uploads/gereja/([a-f0-9]{32})\.(jpg|png|webp)$~i', $path, $matches)) {
        return null;
    }
    return $path;
}
