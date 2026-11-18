<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}
$name = $argv[1] ?? '';
if (!preg_match('/^PW_TEST_[A-F0-9]{32}$/', $name)) {
    fwrite(STDERR, "Nama cleanup bukan nama tes yang valid.\n");
    exit(2);
}

require_once __DIR__ . '/../includes/database.php';
$pdo = getDatabaseConnection();
$select = $pdo->prepare('SELECT id_gereja, foto FROM gereja WHERE nama_gereja = :nama_gereja');
$select->execute(['nama_gereja' => $name]);
$rows = $select->fetchAll();
$delete = $pdo->prepare('DELETE FROM gereja WHERE id_gereja = :id_gereja AND nama_gereja = :nama_gereja');
foreach ($rows as $row) {
    $delete->execute(['id_gereja' => $row['id_gereja'], 'nama_gereja' => $name]);
    $photo = (string) ($row['foto'] ?? '');
    if (preg_match('~^uploads/gereja/([a-f0-9]{32})\.(jpg|png|webp)$~i', $photo)) {
        $path = __DIR__ . '/../' . $photo;
        if (is_file($path)) @unlink($path);
    }
}
fwrite(STDOUT, 'Cleanup data sementara: ' . count($rows) . " baris.\n");
