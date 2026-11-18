<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Skrip ini hanya boleh dijalankan dari terminal lokal.\n");
}

require_once __DIR__ . '/../includes/database.php';

try {
    $pdo = getDatabaseConnection();
    if ((int) $pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() > 0) {
        fwrite(STDERR, "Akun admin sudah tersedia. Pembuatan akun pertama dinonaktifkan.\n");
        exit(1);
    }

    fwrite(STDOUT, "Nama lengkap: ");
    $name = trim((string) fgets(STDIN));
    fwrite(STDOUT, "Username: ");
    $username = trim((string) fgets(STDIN));
    fwrite(STDOUT, "Kata sandi (minimal 12 karakter): ");
    $password = rtrim((string) fgets(STDIN), "\r\n");

    if ($name === '' || mb_strlen($name) > 100 || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        fwrite(STDERR, "Nama lengkap atau username tidak valid. Username: 3–50 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).\n");
        exit(1);
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        fwrite(STDERR, "Kata sandi harus 12–72 byte.\n");
        exit(1);
    }

    $pdo->beginTransaction();
    $lock = $pdo->query('SELECT id_admin FROM admin LIMIT 1 FOR UPDATE')->fetchColumn();
    if ($lock !== false) {
        $pdo->rollBack();
        fwrite(STDERR, "Akun admin sudah tersedia. Pembuatan akun pertama dinonaktifkan.\n");
        exit(1);
    }
    $insert = $pdo->prepare('INSERT INTO admin (username, password_hash, nama_lengkap) VALUES (:username, :password_hash, :nama_lengkap)');
    $insert->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'nama_lengkap' => $name,
    ]);
    $pdo->commit();
    fwrite(STDOUT, "Akun admin berhasil dibuat. Akses http://localhost/webgis_gsja/admin/login.php\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Pembuatan admin pertama gagal: ' . $exception->getMessage());
    fwrite(STDERR, "Pembuatan akun gagal. Periksa tabel admin dan log PHP.\n");
    exit(1);
}
