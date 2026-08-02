import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cssDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/css');
const vipSample = readFileSync(join(__dirname, '../paxdesign-booking/assets/customer-auth/images/avatars-vip/pax-vip-01.gif'));
const sampleUri = `data:image/gif;base64,${vipSample.toString('base64')}`;

const css = [
  'pdx-auth-page.css',
  'pdx-account-app.css',
  'pdx-portal-apple.css',
  'pdx-verified-badge.css',
].map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

const verifiedBadge = '<span class="pdx-verified-badge pdx-verified-badge--inline" role="img" tabindex="0" aria-label="Verified Account" data-pdx-tip="Verified Account"><svg class="pdx-vb" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path class="pdx-vb__shield" d="M12 1.8l8.2 3.2v6.1c0 5.4-3.5 10.2-8.2 11.9-4.7-1.7-8.2-6.5-8.2-11.9V5L12 1.8z"/><path class="pdx-vb__check" d="M8.4 11.9l2.3 2.3 5.1-5.2"/></svg></span>';

function identityBlock(dir) {
  const longName = 'Alexandra Constantinopoulos-Papadimitriou';
  return `<div id="pdx-account-app" class="pdx-account-app" dir="${dir}"><aside class="pdx-account-sidebar"><div class="pdx-account-sidebar-user"><div class="pdx-account-identity">
<span class="pdx-account-avatar pdx-account-avatar--sidebar" style="width:40px;height:40px;flex:0 0 40px"><img class="pdx-account-avatar__img" src="${sampleUri}" width="40" height="40" alt="" /></span>
<div class="pdx-account-sidebar-name-row">
<div class="pdx-account-name-line pdx-account-sidebar-name"><span class="pdx-name-with-badge pdx-name-with-badge--account"><span class="pdx-account-name-text">${longName}</span>${verifiedBadge}</span></div>
<span class="pdx-account-level-badge pdx-account-level-badge--compact">PAXDesign Level 03 — Diamond</span>
<div class="pdx-account-sidebar-email">customer@privaterelay.appleid.com</div>
<div class="pdx-account-sidebar-status">Verified</div>
</div></div></div></aside></div>`;
}

const html = `<!DOCTYPE html><html class="pdx-auth-isolated"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}
.pdx-account-sidebar { width: 280px; padding: 16px; background: #fff; }
.pdx-account-identity { outline: 1px dashed rgba(255,0,0,0.0); }
</style></head><body class="pdx-auth-isolated pdx-account-dashboard-body">${identityBlock('ltr')}${identityBlock('rtl')}</body></html>`;

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

for (const [name, width, height] of [
  ['identity-desktop', 1280, 800],
  ['identity-mobile', 390, 844],
]) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 2 });
  await page.setContent(html, { waitUntil: 'networkidle0' });
  const metrics = await page.evaluate(() => {
    function check(identity) {
      var avatar = identity.querySelector('.pdx-account-avatar');
      var nameText = identity.querySelector('.pdx-account-name-text');
      var badge = identity.querySelector('.pdx-verified-badge');
      var level = identity.querySelector('.pdx-account-level-badge');
      if (!avatar || !nameText || !badge || !level) return { ok: false, reason: 'missing nodes' };
      var ar = avatar.getBoundingClientRect();
      var nr = nameText.getBoundingClientRect();
      var br = badge.getBoundingClientRect();
      var lr = level.getBoundingClientRect();
      var align = getComputedStyle(identity).alignItems;
      var nameClearOfAvatar = nr.left >= ar.right - 2;
      var levelBelowName = lr.top >= nr.bottom - 2;
      var badgeOnNameRow = Math.abs(br.top - nr.top) < 12;
      return {
        ok: nameClearOfAvatar && levelBelowName && badgeOnNameRow && align === 'flex-start',
        align,
        nameClearOfAvatar,
        levelBelowName,
        badgeOnNameRow,
      };
    }
    return Array.from(document.querySelectorAll('.pdx-account-identity')).map(check);
  });
  metrics.forEach((m, i) => {
    if (!m.ok) throw new Error(name + ' block ' + i + ' failed: ' + JSON.stringify(m));
  });
  console.log(`OK: ${name}`);
  await page.close();
}

await browser.close();
console.log('OK: customer name identity layout tests passed');
