<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/database.php';

$actualChurches = [
    [
        'nama_gereja' => 'GSJA Alfa Omega Kakas',
        'alamat' => 'Sendangan, Kec. Kakas',
        'kecamatan' => 'Kakas',
        'latitude' => 1.173857,
        'longitude' => 124.885146,
    ],
    [
        'nama_gereja' => 'GSJA Elshaday Makalelon',
        'alamat' => 'Makalelon, Kec. Kakas',
        'kecamatan' => 'Kakas',
        'latitude' => 1.156374,
        'longitude' => 124.932454,
    ],
    [
        'nama_gereja' => 'GSJA Agape Wineru',
        'alamat' => 'Wineru, Kec. Kakas',
        'kecamatan' => 'Kakas',
        'latitude' => 1.141909,
        'longitude' => 124.952079,
    ],
    [
        'nama_gereja' => 'GSJA Parakletos Kayuwatu',
        'alamat' => 'Kayuwatu, Kec. Kakas',
        'kecamatan' => 'Kakas',
        'latitude' => 1.128278,
        'longitude' => 124.962982,
    ],
    [
        'nama_gereja' => 'GSJA Getsemani Toulimembet',
        'alamat' => 'Toulimembet, Kec. Kakas',
        'kecamatan' => 'Kakas',
        'latitude' => 1.198659,
        'longitude' => 124.905678,
    ],
    [
        'nama_gereja' => 'GSJA Charisma Kakas',
        'alamat' => 'Pahaleten, Kec. Kakas',
        'kecamatan' => 'Kakas',
        'latitude' => 1.173541,
        'longitude' => 124.875162,
    ],
    [
        'nama_gereja' => 'GSJA Sola Gratia Tandengan',
        'alamat' => 'Tandengan, Kec. Eris',
        'kecamatan' => 'Eris',
        'latitude' => 1.233313,
        'longitude' => 124.922325,
    ],
    [
        'nama_gereja' => 'GSJA Soli Deo Rinondor',
        'alamat' => 'Rinondor, Kec. Kakas',
        'kecamatan' => 'Kakas',
        'latitude' => 1.148973,
        'longitude' => 124.922135,
    ],
    [
        'nama_gereja' => 'GSJA Shalom Koya',
        'alamat' => 'Koya, Kec. Tondano Barat',
        'kecamatan' => 'Tondano Barat',
        'latitude' => 1.291157,
        'longitude' => 124.896449,
    ],
    [
        'nama_gereja' => 'GSJA Kota Kemuliaan Tondano',
        'alamat' => 'Tuutu, Kec. Tondano Barat',
        'kecamatan' => 'Tondano Barat',
        'latitude' => 1.297483,
        'longitude' => 124.908128,
    ],
    [
        'nama_gereja' => 'GSJA Life for Church Liningaan',
        'alamat' => 'Liningaan, Kec. Tondano Timur',
        'kecamatan' => 'Tondano Timur',
        'latitude' => 1.299306,
        'longitude' => 124.913859,
    ],
    [
        'nama_gereja' => 'GSJA Integrity Kembuan',
        'alamat' => 'Kembuan Satu, Kec. Tondano Utara',
        'kecamatan' => 'Tondano Utara',
        'latitude' => 1.328609,
        'longitude' => 124.904228,
    ],
    [
        'nama_gereja' => 'GSJA El Gibbor Kembuan',
        'alamat' => 'Kembuan, Kec. Tondano Utara',
        'kecamatan' => 'Tondano Utara',
        'latitude' => 1.320028,
        'longitude' => 124.916774,
    ],
    [
        'nama_gereja' => 'GSJA Soli Deo Tonsea Lama',
        'alamat' => 'Tonsea Lama, Kec. Tondano Utara',
        'kecamatan' => 'Tondano Utara',
        'latitude' => 1.321077,
        'longitude' => 124.922205,
    ],
];

try {
    $pdo = getDatabaseConnection();
    $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM gereja WHERE nama_gereja = :nama_gereja');
    $stmtInsert = $pdo->prepare('INSERT INTO gereja (nama_gereja, alamat, kecamatan, kabupaten, daerah, wilayah, latitude, longitude, foto) VALUES (:nama_gereja, :alamat, :kecamatan, "Minahasa", "Sulawesi Utara 2", "Wilayah 2", :latitude, :longitude, NULL)');

    $inserted = 0;
    foreach ($actualChurches as $church) {
        $stmtCheck->execute(['nama_gereja' => $church['nama_gereja']]);
        if ((int) $stmtCheck->fetchColumn() === 0) {
            $stmtInsert->execute([
                'nama_gereja' => $church['nama_gereja'],
                'alamat' => $church['alamat'],
                'kecamatan' => $church['kecamatan'],
                'latitude' => $church['latitude'],
                'longitude' => $church['longitude'],
            ]);
            $inserted++;
        }
    }

    echo "Selesai. Berhasil menambahkan {$inserted} data gereja aktual dari Tabel 2 Jurnal.\n";
    $total = (int) $pdo->query('SELECT COUNT(*) FROM gereja')->fetchColumn();
    echo "Total gereja saat ini: {$total}\n";
} catch (Throwable $e) {
    echo "Gagal: " . $e->getMessage() . "\n";
    exit(1);
}
