import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const css = [
  'pdx-auth-page.css',
  'pdx-account-app.css',
  'pdx-portal-apple.css',
].map((f) => readFileSync(join(__dirname, '../paxdesign-booking/assets/customer-auth/css', f), 'utf8')).join('\n');

const html = `<!DOCTYPE html><html class="pdx-auth-isolated"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head>
<body class="pdx-auth-isolated pdx-account-dashboard-body"><div id="pdx-account-signout-confirm" class="pdx-account-signout-confirm">
<div class="pdx-account-signout-confirm__backdrop"></div>
<div class="pdx-account-signout-confirm__sheet">
<h2 class="pdx-account-signout-confirm__title">Sign Out?</h2>
<p class="pdx-account-signout-confirm__message">Are you sure you want to sign out?</p>
<div class="pdx-account-signout-confirm__actions">
<button type="button" class="pdx-portal-btn pdx-portal-btn--secondary pdx-account-signout-confirm__cancel">Cancel</button>
<button type="button" class="pdx-portal-btn pdx-portal-btn--destructive pdx-account-signout-confirm__confirm">Sign Out</button>
</div></div></div></body></html>`;

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

for (const [name, width, height] of [
  ['signout-confirm-desktop', 1280, 800],
  ['signout-confirm-mobile', 390, 844],
]) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 2 });
  await page.setContent(html, { waitUntil: 'networkidle0' });
  const metrics = await page.evaluate(() => {
    function btn(name) {
      var el = document.querySelector(name);
      if (!el) return { exists: false };
      var s = getComputedStyle(el);
      return {
        exists: true,
        text: (el.textContent || '').trim(),
        color: s.color,
        background: s.backgroundColor,
        display: s.display,
        opacity: s.opacity,
        visibility: s.visibility,
      };
    }
    return { cancel: btn('.pdx-account-signout-confirm__cancel'), confirm: btn('.pdx-account-signout-confirm__confirm') };
  });
  if (!metrics.cancel.exists || !metrics.confirm.exists) throw new Error(name + ' missing buttons');
  if (!metrics.cancel.text || !metrics.confirm.text) throw new Error(name + ' button text missing: ' + JSON.stringify(metrics));
  if (metrics.confirm.display === 'none' || metrics.confirm.visibility === 'hidden') throw new Error(name + ' confirm button hidden');
  const confirmBg = metrics.confirm.background || '';
  if (!confirmBg.includes('255, 59, 48') && !confirmBg.includes('255, 69, 58')) throw new Error(name + ' confirm button not red: ' + confirmBg);
  if (metrics.confirm.color !== 'rgb(255, 255, 255)') throw new Error(name + ' confirm text not white: ' + metrics.confirm.color);
  console.log(`OK: ${name}`, metrics);
  await page.close();
}

await browser.close();
console.log('OK: sign-out confirmation button visibility tests passed');
