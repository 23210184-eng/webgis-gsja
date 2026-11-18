import { expect, test } from '@playwright/test';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { cleanupTestChurch, loginAsAdmin, uniqueTestChurchName } from './helpers';

test('CRUD, validasi, koordinat, foto, integrasi peta publik, dan cleanup data sementara', async ({ page }) => {
  test.skip(!process.env.WEBGIS_ADMIN_USERNAME || !process.env.WEBGIS_ADMIN_PASSWORD, 'Set WEBGIS_ADMIN_USERNAME dan WEBGIS_ADMIN_PASSWORD.');
  const name = uniqueTestChurchName();
  const testDir = resolve('test-results', 'fixtures');
  const validImage = resolve(testDir, 'pw-test.png');
  const invalidFile = resolve(testDir, 'pw-test.txt');

  try {
    await mkdir(testDir, { recursive: true });
    await writeFile(validImage, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/S2sAAAAASUVORK5CYII=', 'base64'));
    await writeFile(invalidFile, 'not an image file');
    await loginAsAdmin(page);

    await test.step('Tambah data sementara dari panel admin', async () => {
      await page.goto('admin/gereja_form.php');
      await page.getByLabel(/Nama gereja/).fill(name);
      await page.getByLabel(/Kecamatan/).selectOption('Kakas');
      await page.getByLabel(/Alamat\/desa atau kelurahan/).fill('Alamat sementara Playwright');
      await page.getByLabel('Kabupaten').fill('Minahasa');
      await page.getByLabel('Daerah').fill('Sulawesi Utara 2');
      await page.getByLabel('Wilayah').fill('Wilayah 2');

      await page.locator('#coordinate-map').click({ position: { x: 220, y: 180 } });
      await expect(page.locator('#latitude')).not.toHaveValue('');
      await expect(page.locator('#longitude')).not.toHaveValue('');
      await page.locator('#latitude').fill('1.2000000');
      await page.locator('#longitude').fill('124.9000000');
      await page.getByRole('button', { name: 'Simpan data' }).click();
      await expect(page).toHaveURL(/admin\/index\.php$/);
      await expect(page.getByRole('row').filter({ hasText: name })).toBeVisible();
    });

    await test.step('Validasi field kosong dan koordinat di luar rentang', async () => {
      await page.getByRole('row').filter({ hasText: name }).getByRole('link', { name: 'Edit' }).click();
      const churchName = page.getByLabel(/Nama gereja/);
      await churchName.evaluate((element: HTMLInputElement) => element.removeAttribute('required'));
      await churchName.fill('');
      await page.getByRole('button', { name: 'Simpan data' }).click();
      await expect(page.getByRole('alert')).toContainText('Nama gereja wajib diisi');
      await churchName.fill(name);

      await page.locator('#latitude').evaluate((element: HTMLInputElement) => element.removeAttribute('max'));
      await page.locator('#latitude').fill('91');
      await page.locator('#longitude').fill('124.9');
      await page.getByRole('button', { name: 'Simpan data' }).click();
      await expect(page.getByRole('alert')).toContainText('Koordinat tidak valid');
      await page.locator('#latitude').evaluate((element: HTMLInputElement) => element.setAttribute('max', '90'));
      await page.locator('#latitude').fill('1.2');
      await page.locator('#longitude').fill('124.9');
    });

    await test.step('Tolak berkas bukan gambar dan menerima PNG valid', async () => {
      const fileInput = page.locator('input[type="file"][name="foto"]');
      await fileInput.setInputFiles(invalidFile);
      await page.getByRole('button', { name: 'Simpan data' }).click();
      await expect(page.getByRole('alert')).toContainText('Format foto harus JPG');

      await fileInput.setInputFiles(validImage);
      await page.getByLabel(/Alamat\/desa atau kelurahan/).fill('Alamat sementara Playwright setelah edit');
      await page.getByRole('button', { name: 'Simpan data' }).click();
      await expect(page).toHaveURL(/admin\/index\.php$/);
      await expect(page.getByRole('row').filter({ hasText: name })).toContainText('Lihat foto');
    });

    await test.step('Perubahan admin tersedia pada API dan peta publik', async () => {
      const apiResponse = await page.request.get('api/gereja.php');
      expect(apiResponse.ok()).toBeTruthy();
      const payload = await apiResponse.json();
      const record = payload.gereja.find((church: any) => church.nama === name);
      expect(record).toBeTruthy();
      expect(record.alamat).toBe('Alamat sementara Playwright setelah edit');
      expect(record.latitude).toBeCloseTo(1.2, 5);
      expect(record.longitude).toBeCloseTo(124.9, 5);
      expect(record.foto).toMatch(/^uploads\/gereja\/[a-f0-9]{32}\.png$/i);

      await page.goto('/');
      await page.getByRole('searchbox', { name: 'Cari gereja' }).fill(name);
      await page.getByRole('option').filter({ hasText: name }).click();
      await expect(page.locator('.leaflet-popup-content')).toContainText('Alamat sementara Playwright setelah edit');
      await expect(page.locator('.leaflet-popup-content img')).toBeVisible();
      await page.getByLabel('Filter kecamatan').selectOption('Kakas');
      const districtResponse = await page.request.get('api/gereja.php?kecamatan=Kakas');
      const districtData = await districtResponse.json();
      const validDistrictCount = districtData.gereja.filter((church: any) => Number.isFinite(church.latitude) && Number.isFinite(church.longitude)).length;
      await expect(page.locator('.leaflet-marker-icon')).toHaveCount(validDistrictCount);
    });

    await test.step('Hapus hanya record sementara yang dibuat tes', async () => {
      await page.goto('admin/index.php');
      const row = page.getByRole('row').filter({ hasText: name });
      page.once('dialog', (dialog) => dialog.accept());
      await row.locator('form[action="gereja_hapus.php"] button[type="submit"]').click();
      await expect(page.getByRole('row').filter({ hasText: name })).toHaveCount(0);
      const response = await page.request.get('api/gereja.php');
      const payload = await response.json();
      expect(payload.gereja.some((church: any) => church.nama === name)).toBeFalsy();
    });
  } finally {
    try {
      cleanupTestChurch(name);
    } finally {
      await rm(validImage, { force: true });
      await rm(invalidFile, { force: true });
    }
  }
});
