<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metode tidak diizinkan.');
}
verifyCsrfToken();
$id = filter_var($_POST['id_gereja'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    http_response_code(400);
    exit('ID data tidak valid.');
}

try {
    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare('SELECT foto FROM gereja WHERE id_gereja = :id');
    $statement->execute(['id' => $id]);
    $photo = safePhotoPath($statement->fetchColumn() ?: null);
    $delete = $pdo->prepare('DELETE FROM gereja WHERE id_gereja = :id');
    $delete->execute(['id' => $id]);
    if ($delete->rowCount() === 0) {
        setFlash('error', 'Data gereja tidak ditemukan.');
    } else {
        if ($photo !== null) {
            $photoFile = __DIR__ . '/../' . $photo;
            if (is_file($photoFile)) @unlink($photoFile);
        }
        setFlash('success', 'Data gereja berhasil dihapus.');
    }
} catch (Throwable $exception) {
    error_log('Hapus data gereja gagal: ' . $exception->getMessage());
    setFlash('error', 'Data belum dapat dihapus. Periksa database dan log PHP.');
}
header('Location: index.php');
exit;
