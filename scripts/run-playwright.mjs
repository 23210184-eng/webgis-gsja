import { existsSync, readFileSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const cli = resolve(root, 'node_modules/@playwright/test/cli.js');
const resultFile = resolve(root, 'test-results/results.json');
const reportFile = resolve(root, 'test-results/report.md');
mkdirSync(dirname(reportFile), { recursive: true });
rmSync(resultFile, { force: true });

if (!existsSync(cli)) {
  const scenarios = [
    'Halaman utama, peta, dan API publik',
    'Marker koordinat valid dan popup',
    'Pencarian nama gereja',
    'Filter lima kecamatan dan reset ke semua',
    'Halaman login admin',
    'Login kredensial salah',
    'Pembatasan dashboard tanpa login',
    'Login benar, dashboard, logout, dan akses setelah logout',
    'CRUD PW_TEST_, validasi, koordinat, upload, integrasi publik, dan cleanup',
  ];
  const rows = scenarios.map((name) => `| ${name} | NOT RUN | Dependency \`@playwright/test\` belum terpasang. | — |`).join('\n');
  writeFileSync(reportFile, `# Laporan pengujian WebGIS GSJA\n\n**Status: NOT RUN** — npm dependency belum tersedia di cache offline dan tes belum dieksekusi.\n\n| Skenario | Hasil | Pesan | Screenshot |\n|---|---|---|---|\n${rows}\n`, 'utf8');
  console.error('Playwright belum terpasang. Laporan disimpan di test-results/report.md.');
  process.exit(2);
}

const result = spawnSync(process.execPath, [cli, 'test', ...process.argv.slice(2)], { cwd: root, stdio: 'inherit', env: process.env });
let markdown = '# Laporan pengujian WebGIS GSJA\n\n';
if (existsSync(resultFile)) {
  const data = JSON.parse(readFileSync(resultFile, 'utf8'));
  const cases = [];
  const visit = (suite, parents = []) => {
    for (const spec of suite.specs || []) {
      for (const testCase of spec.tests || []) {
        const last = (testCase.results || []).at(-1) || {};
        cases.push({
          name: [...parents, spec.title].join(' / '),
          status: testCase.status || last.status || 'unknown',
          error: last.error?.message || last.errors?.map((error) => error.message).filter(Boolean).join('\n') || '',
          attachments: (last.attachments || []).filter((attachment) => /screenshot/i.test(attachment.name)).map((attachment) => attachment.path || attachment.name),
        });
      }
    }
    for (const child of suite.suites || []) visit(child, [...parents, child.title].filter(Boolean));
  };
  for (const suite of data.suites || []) visit(suite);
  const summary = data.stats || {};
  markdown += `- Total: ${summary.total ?? cases.length}\n- PASS: ${summary.expected ?? cases.filter((item) => item.status === 'expected').length}\n- FAIL: ${summary.unexpected ?? cases.filter((item) => item.status === 'unexpected').length}\n- SKIP: ${summary.skipped ?? cases.filter((item) => item.status === 'skipped').length}\n\n`;
  markdown += '| Skenario | Hasil | Pesan error | Screenshot kegagalan |\n|---|---|---|---|\n';
  for (const item of cases) {
    const status = item.status === 'expected' ? 'PASS' : item.status === 'unexpected' ? 'FAIL' : item.status.toUpperCase();
    const error = (item.error || '—').replaceAll('|', '\\|').replaceAll('\n', '<br>');
    const screenshots = item.attachments.length ? item.attachments.map((path) => `\`${path}\``).join('<br>') : '—';
    markdown += `| ${item.name.replaceAll('|', '\\|')} | ${status} | ${error} | ${screenshots} |\n`;
  }
} else {
  markdown += '**Status: NOT RUN** — Playwright tidak menghasilkan berkas JSON hasil.\n';
}
writeFileSync(reportFile, markdown, 'utf8');
process.exit(result.status ?? 1);
