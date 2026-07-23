/**
 * Design smoke — reps.decisionsciencecorp.com
 * Usage: node capture.mjs [baseUrl]
 * Default baseUrl: http://127.0.0.1:8787
 */
import { chromium } from "playwright";
import { mkdirSync } from "fs";
import { dirname, join } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const out = join(__dirname, "output");
mkdirSync(out, { recursive: true });
const base = process.argv[2] || "http://127.0.0.1:8787";

const browser = await chromium.launch({ headless: true });
for (const [name, size] of [
  ["mobile", { width: 390, height: 844 }],
  ["desktop", { width: 1280, height: 800 }],
]) {
  const page = await browser.newPage({ viewport: size });
  await page.goto(base + "/", { waitUntil: "networkidle" });
  await page.waitForTimeout(800);
  await page.screenshot({ path: join(out, `reps-hero-${name}.png`), fullPage: false });
  await page.screenshot({ path: join(out, `reps-full-${name}.png`), fullPage: true });
  await page.close();
}
await browser.close();
console.log("wrote", out);
