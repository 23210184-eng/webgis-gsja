<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Metode tidak diizinkan.']);
    exit;
}

$allowedDistricts = ['Kakas', 'Eris', 'Tondano Barat', 'Tondano Timur', 'Tondano Utara'];
$district = trim((string) ($_GET['kecamatan'] ?? ''));
if ($district !== '' && !in_array($district, $allowedDistricts, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Kecamatan tidak valid.']);
    exit;
}

try {
    require_once __DIR__ . '/../includes/database.php';
    $pdo = getDatabaseConnection();

    $total = (int) $pdo->query('SELECT COUNT(*) FROM gereja')->fetchColumn();
    $sql = 'SELECT id_gereja, nama_gereja, alamat, kecamatan, kabupaten, latitude, longitude, foto FROM gereja';
    if ($district !== '') {
        $statement = $pdo->prepare($sql . ' WHERE kecamatan = :kecamatan ORDER BY nama_gereja');
        $statement->execute(['kecamatan' => $district]);
    } else {
        $statement = $pdo->prepare($sql . ' ORDER BY nama_gereja');
        $statement->execute();
    }

    $extractDesa = static function (string $alamat): string {
        $alamat = trim($alamat);
        if ($alamat === '') {
            return '—';
        }
        $parts = explode(',', $alamat);
        $first = trim($parts[0]);
        $cleaned = preg_replace('/^(Desa|Kelurahan)\s+/i', '', $first);
        return $cleaned !== '' ? $cleaned : $first;
    };

    $churches = [];
    foreach ($statement->fetchAll() as $row) {
        $latitude = is_numeric($row['latitude']) ? (float) $row['latitude'] : null;
        $longitude = is_numeric($row['longitude']) ? (float) $row['longitude'] : null;
        $coordinatesValid = $latitude !== null && $longitude !== null
            && is_finite($latitude) && is_finite($longitude)
            && $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;

        $photo = trim((string) ($row['foto'] ?? ''));
        if ($photo !== '' && !preg_match('~^uploads/gereja/[A-Za-z0-9._-]+$~', $photo)) {
            $photo = '';
        }

        $churches[] = [
            'id' => (int) $row['id_gereja'],
            'nama' => (string) $row['nama_gereja'],
            'alamat' => (string) $row['alamat'],
            'desa_kelurahan' => $extractDesa((string) $row['alamat']),
            'kecamatan' => (string) $row['kecamatan'],
            'kabupaten' => (string) ($row['kabupaten'] ?? 'Minahasa'),
            'latitude' => $coordinatesValid ? $latitude : null,
            'longitude' => $coordinatesValid ? $longitude : null,
            'foto' => $photo !== '' ? $photo : null,
        ];
    }

    echo json_encode([
        'total' => $total,
        'jumlah_hasil' => count($churches),
        'gereja' => $churches,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    error_log('Gagal mengambil data peta gereja: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Data gereja belum dapat dimuat. Periksa koneksi database.'], JSON_UNESCAPED_UNICODE);
}
