-- Jalankan file ini melalui phpMyAdmin atau MySQL CLI.
CREATE DATABASE IF NOT EXISTS webgis_gsja
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE webgis_gsja;

CREATE TABLE IF NOT EXISTS gereja (
    id_gereja INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_gereja VARCHAR(150) NOT NULL,
    alamat TEXT NOT NULL,
    kecamatan VARCHAR(100) NOT NULL,
    kabupaten VARCHAR(100) NOT NULL DEFAULT 'Minahasa',
    daerah VARCHAR(100) NOT NULL DEFAULT 'Sulawesi Utara 2',
    wilayah VARCHAR(100) NOT NULL DEFAULT 'Wilayah 2',
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    foto VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gereja_kecamatan (kecamatan),
    INDEX idx_gereja_nama (nama_gereja),
    CONSTRAINT chk_gereja_latitude CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90),
    CONSTRAINT chk_gereja_longitude CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin (
    id_admin INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Buat hash kata sandi dengan PHP password_hash() sebelum menambahkan admin.
-- Jangan menyimpan kata sandi teks biasa di tabel ini.
