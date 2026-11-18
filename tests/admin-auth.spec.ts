import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers';

const username = process.env.WEBGIS_ADMIN_USERNAME;
const password = process.env.WEBGIS_ADMIN_PASSWORD;
const hasCredentials = Boolean(username && password);

test('Halaman login admin dapat dibuka', async ({ page }) => {
  await page.goto('admin/login.php');
  await expect(page.getByRole('heading', { name: 'Login Admin' })).toBeVisible();
  await expect(page.getByLabel('Username')).toBeVisible();
  await expect(page.getByLabel('Kata sandi')).toBeVisible();
});

test('Login dengan kredensial salah ditolak', async ({ page }) => {
  test.skip(!hasCredentials, 'Set WEBGIS_ADMIN_USERNAME dan WEBGIS_ADMIN_PASSWORD.');
  await page.goto('admin/login.php');
  await page.getByLabel('Username').fill(`${username}_INVALID`);
  await page.getByLabel('Kata sandi').fill(`PW_INVALID_${Date.now()}`);
  await page.getByRole('button', { name: 'Masuk' }).click();
  await expect(page.getByRole('alert')).toContainText('Username atau kata sandi tidak sesuai');
  await expect(page).toHaveURL(/admin\/login\.php$/);
});

test('Dashboard admin memerlukan login', async ({ page }) => {
  await page.goto('admin/index.php');
  await expect(page).toHaveURL(/admin\/login\.php$/);
  await expect(page.getByRole('heading', { name: 'Login Admin' })).toBeVisible();
});

test('Login benar, dashboard terbuka, logout, lalu dashboard kembali terkunci', async ({ page }) => {
  test.skip(!hasCredentials, 'Set WEBGIS_ADMIN_USERNAME dan WEBGIS_ADMIN_PASSWORD.');
  await loginAsAdmin(page);
  await expect(page.getByRole('heading', { name: 'Dashboard admin' })).toBeVisible();
  await page.getByRole('button', { name: 'Logout' }).click();
  await expect(page).toHaveURL(/admin\/login\.php$/);
  await page.goto('admin/index.php');
  await expect(page).toHaveURL(/admin\/login\.php$/);
});
