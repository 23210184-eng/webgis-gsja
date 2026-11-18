# WebGIS Pemetaan Gereja GSJA

Aplikasi PHP Native untuk peta publik dan pengelolaan data gereja GSJA Kabupaten Minahasa, Sulawesi Utara 2, Wilayah 2.

## Menjalankan

1. Salin `config/database.example.php` menjadi `config/database.php`, lalu sesuaikan host, port, nama database, username, dan password dengan pengaturan MySQL lokal. File `config/database.php` diabaikan oleh Git agar kredensial lokal tidak ikut dipublikasikan.
   ```powershell
   Copy-Item config/database.example.php config/database.php
   ```
2. Jalankan Apache dan MySQL di XAMPP.
3. Impor `database/schema.sql` melalui phpMyAdmin jika belum dilakukan.
4. Buat admin pertama satu kali dari terminal lokal:

   ```powershell
   cd D:\xampp\htdocs\webgis_gsja
   D:\xampp\php\php.exe scripts\create_admin.php
   ```

   Skrip meminta nama lengkap, username, dan kata sandi (minimal 12 karakter). Skrip hanya berjalan melalui PHP CLI dan menolak berjalan jika tabel `admin` sudah memiliki akun. Tidak ada kredensial default di kode.
5. Buka `http://localhost/webgis_gsja/admin/login.php`, lalu masuk menggunakan akun yang dibuat.

## Panel admin

- `admin/login.php`, `admin/logout.php` — autentikasi session dan logout POST.
- `admin/index.php` — dashboard, ringkasan per kecamatan, daftar serta aksi data.
- `admin/gereja_form.php` — tambah/edit data dan peta pemilih koordinat.
- `admin/gereja_hapus.php` — penghapusan melalui POST dengan CSRF.
- `admin/includes/bootstrap.php` — session, autentikasi, CSRF, escaping, dan tampilan umum.
- `scripts/create_admin.php` — bootstrap akun pertama, CLI-only.
- `assets/css/admin.css`, `assets/js/admin-map.js` — tampilan panel dan pemilih koordinat Leaflet.

Formulir mengizinkan JPG, PNG, dan WEBP sampai 4 MB; file diberi nama acak dan disimpan di `uploads/gereja/`. Foto lama dihapus setelah pergantian foto atau penghapusan data berhasil. Pengunjung dapat melihat data tersimpan melalui peta publik.

## Pengujian otomatis Playwright

Pasang dependensi dan browser Chromium satu kali:

```powershell
npm install
npm run test:e2e:install
```

Pastikan XAMPP Apache/MySQL aktif. Siapkan kredensial admin melalui environment variables (password dimasukkan secara interaktif agar tidak ditulis ke source code):

```powershell
$env:WEBGIS_ADMIN_USERNAME = Read-Host 'Username admin'
$securePassword = Read-Host 'Password admin' -AsSecureString
$env:WEBGIS_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $securePassword).Password
$env:WEBGIS_PHP = 'D:\xampp\php\php.exe'
npm run test:e2e
Remove-Item Env:WEBGIS_ADMIN_USERNAME, Env:WEBGIS_ADMIN_PASSWORD, Env:WEBGIS_PHP
```

Tes CRUD membuat satu baris sementara bernama unik `PW_TEST_...`, menguji API dan peta, lalu menghapus hanya nama tersebut lewat `tests/cleanup-test-church.php` dalam blok `finally`. Jangan jalankan tes CRUD bersamaan dengan sesi admin yang sedang mengedit data. Hasil teks ada di `test-results/report.md`, hasil mentah JSON di `test-results/results.json`, dan laporan lengkap Playwright di `playwright-report/index.html`. Screenshot kegagalan tersimpan di `test-results/artifacts/`.

## Keamanan

Semua query dinamis memakai PDO prepared statements. Formulir perubahan data dan logout memakai token CSRF. Session ID diregenerasi setelah login. Output panel di-escape, koordinat dan kecamatan divalidasi, dan error rinci dicatat melalui `error_log()` tanpa ditampilkan kepada pengunjung.
