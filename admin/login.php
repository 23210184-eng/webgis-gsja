<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrfToken();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        $statement = getDatabaseConnection()->prepare(
            'SELECT id_admin, username, nama_lengkap, password_hash FROM admin WHERE username = :username LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $admin = $statement->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id_admin'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['nama_lengkap'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: index.php');
            exit;
        }
        $error = 'Username atau kata sandi tidak sesuai.';
    } catch (Throwable $exception) {
        error_log('Login admin gagal: ' . $exception->getMessage());
        $error = 'Login belum dapat diproses. Pastikan database tersedia.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin · WebGIS GSJA</title><link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <a class="back-link" href="../index.php">← Kembali ke peta publik</a>
    <h1>Login Admin</h1>
    <p>Masuk untuk mengelola data gereja GSJA.</p>
    <?php if ($error !== ''): ?><p class="flash error" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <label>Username<input name="username" type="text" maxlength="50" autocomplete="username" required autofocus></label>
        <label>Kata sandi<input name="password" type="password" autocomplete="current-password" required></label>
        <button class="button primary" type="submit">Masuk</button>
    </form>
    <p class="small-note">Akun pertama dibuat melalui utilitas lokal `scripts/create_admin.php`.</p>
</main>
</body>
</html>
