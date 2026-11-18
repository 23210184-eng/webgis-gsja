import { execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { resolve } from 'node:path';
import type { Page } from '@playwright/test';

export function uniqueTestChurchName(): string {
  return `PW_TEST_${randomBytes(16).toString('hex').toUpperCase()}`;
}

export async function loginAsAdmin(page: Page): Promise<void> {
  const username = process.env.WEBGIS_ADMIN_USERNAME;
  const password = process.env.WEBGIS_ADMIN_PASSWORD;
  if (!username || !password) throw new Error('Set WEBGIS_ADMIN_USERNAME dan WEBGIS_ADMIN_PASSWORD untuk tes admin.');

  await page.goto('admin/login.php');
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Kata sandi').fill(password);
  await Promise.all([
    page.waitForURL('**/admin/index.php'),
    page.getByRole('button', { name: 'Masuk' }).click(),
  ]);
}

export function cleanupTestChurch(name: string): void {
  const php = process.env.WEBGIS_PHP || 'D:\\xampp\\php\\php.exe';
  const helper = resolve('tests', 'cleanup-test-church.php');
  execFileSync(php, [helper, name], { stdio: 'pipe', windowsHide: true });
}
