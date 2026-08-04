/**
 * Slice A visual smoke — login + home @ mobile/desktop.
 * Usage: node dashboard-slice-a.mjs [baseUrl]
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const base = (process.argv[2] || 'https://reps.decisionsciencecorp.com').replace(/\/$/, '');
const outDir = path.join(__dirname, 'output', 'dashboard-slice-a');
fs.mkdirSync(outDir, { recursive: true });

const viewports = [
  { name: 'mobile', width: 390, height: 844 },
  { name: 'desktop', width: 1280, height: 800 },
];

const browser = await chromium.launch();
for (const vp of viewports) {
  const context = await browser.newContext({ viewport: vp });
  const page = await context.newPage();
  await page.goto(`${base}/dashboard/login.php`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(outDir, `${vp.name}-login.png`), fullPage: true });
  await page.click('button:has-text("Mark Hopkins")');
  await page.waitForURL('**/dashboard/**');
  await page.screenshot({ path: path.join(outDir, `${vp.name}-home.png`), fullPage: true });
  await page.goto(`${base}/dashboard/shops.php`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(outDir, `${vp.name}-shops.png`), fullPage: true });
  await context.close();
}
await browser.close();
console.log('Wrote screenshots to', outDir);
