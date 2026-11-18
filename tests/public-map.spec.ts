import { expect, test } from '@playwright/test';

test('Peta publik tampil dan API mengembalikan data', async ({ page }) => {
  const apiResponse = page.waitForResponse((response) => response.url().includes('/api/gereja.php'));
  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'WebGIS Pemetaan Gereja GSJA' })).toBeVisible();
  await expect(page.locator('#map.leaflet-container')).toBeVisible();

  const response = await apiResponse;
  expect(response.ok()).toBeTruthy();
  const payload = await response.json();
  expect(payload).toMatchObject({ total: expect.any(Number), jumlah_hasil: expect.any(Number), gereja: expect.any(Array) });
});

test('Marker dengan koordinat valid dan popup tampil', async ({ page }) => {
  const response = await page.request.get('api/gereja.php');
  expect(response.ok()).toBeTruthy();
  const payload = await response.json();
  const validChurches = payload.gereja.filter((church: any) =>
    Number.isFinite(church.latitude) && Number.isFinite(church.longitude)
    && church.latitude >= -90 && church.latitude <= 90
    && church.longitude >= -180 && church.longitude <= 180,
  );

  test.skip(validChurches.length === 0, 'Belum ada data database dengan koordinat valid.');
  await page.goto('/');
  await expect(page.locator('.leaflet-marker-icon')).toHaveCount(validChurches.length);
  await page.locator('.leaflet-marker-icon').first().click();
  await expect(page.locator('.leaflet-popup-content')).toContainText(validChurches[0].nama);
  await expect(page.locator('.leaflet-popup-content')).toContainText(validChurches[0].kecamatan);
});

test('Pencarian gereja berdasarkan nama', async ({ page }) => {
  const response = await page.request.get('api/gereja.php');
  expect(response.ok()).toBeTruthy();
  const payload = await response.json();
  await page.goto('/');
  const search = page.getByRole('searchbox', { name: 'Cari gereja' });

  if (payload.gereja.length === 0) {
    await search.fill('gereja-yang-tidak-ada');
    await expect(page.locator('#search-results')).toContainText('Gereja tidak ditemukan');
    return;
  }

  const church = payload.gereja[0];
  await search.fill(church.nama);
  const result = page.getByRole('option').filter({ hasText: church.nama }).first();
  await expect(result).toBeVisible();
  await result.click();
  if (church.latitude !== null && church.longitude !== null) {
    await expect(page.locator('.leaflet-popup-content')).toContainText(church.nama);
  } else {
    await expect(page.locator('#map-status')).toContainText('Koordinat');
  }
});

test('Filter lima kecamatan dan kembali ke semua kecamatan', async ({ page }) => {
  await page.goto('/');
  const filter = page.getByLabel('Filter kecamatan');
  for (const district of ['Kakas', 'Eris', 'Tondano Barat', 'Tondano Timur', 'Tondano Utara']) {
    const expected = page.waitForResponse((response) => response.url().includes('api/gereja.php?kecamatan='));
    await filter.selectOption(district);
    const filteredResponse = await expected;
    expect(filteredResponse.ok()).toBeTruthy();
    const filtered = await filteredResponse.json();
    expect(filtered.gereja.every((church: any) => church.kecamatan === district)).toBeTruthy();
    await expect(page.locator('#church-count')).toHaveText(String(filtered.jumlah_hasil));
  }

  const allResponse = page.waitForResponse((response) => /\/api\/gereja\.php(?:\?|$)/.test(response.url()) && !response.url().includes('kecamatan='));
  await filter.selectOption('');
  const all = await allResponse;
  expect(all.ok()).toBeTruthy();
  const allPayload = await all.json();
  expect(allPayload.gereja.length).toBe(allPayload.total);
});
