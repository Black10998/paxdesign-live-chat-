import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cssDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/css');
const css = ['pdx-auth.css', 'pdx-customer-ui.css', 'pdx-account-app.css'].map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

const levelLabel = 'PAXDesign Level 01 — Gold';
const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}
#pdx-auth-bar { position: relative; padding: 20px; }
.pdx-auth-menu { display: block !important; position: relative; top: 0; right: auto; }
</style></head><body><div id="pdx-auth-bar" class="pdx-cx-shell pdx-auth-bar--header"><div class="pdx-auth-menu is-open">
<div class="pdx-auth-menu-head"><div class="pdx-auth-menu-identity"><div class="pdx-auth-menu-identity-text">
<div class="pdx-auth-menu-name">Premium Customer</div>
<span class="pdx-account-level-badge pdx-account-level-badge--compact pdx-account-level-badge--menu">${levelLabel}</span>
<div class="pdx-auth-menu-email">customer@example.com</div>
<div class="pdx-auth-menu-status">Verified</div>
</div></div></div></div></div></body></html>`;

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

for (const [name, width, height] of [
  ['header-profile-level-desktop', 1280, 800],
  ['header-profile-level-mobile', 390, 844],
]) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 2 });
  await page.setContent(html, { waitUntil: 'networkidle0' });
  const metrics = await page.evaluate((expected) => {
    var badge = document.querySelector('.pdx-account-level-badge--menu');
    if (!badge) return { ok: false, reason: 'missing badge' };
    var r = badge.getBoundingClientRect();
    var s = getComputedStyle(badge);
    return {
      ok: r.width > 0 && r.height > 0 && (badge.textContent || '').includes('Level 01') && s.display !== 'none',
      text: badge.textContent,
      width: r.width,
      height: r.height,
      display: s.display,
    };
  }, levelLabel);
  if (!metrics.ok) throw new Error(name + ' failed: ' + JSON.stringify(metrics));
  console.log(`OK: ${name}`, metrics);
  await page.close();
}

await browser.close();
console.log('OK: header profile level badge tests passed');
