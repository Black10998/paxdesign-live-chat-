import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cssDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/css');
const vipSample = readFileSync(join(__dirname, '../paxdesign-booking/assets/customer-auth/images/avatars-vip/pax-vip-01.gif'));
const sampleUri = `data:image/gif;base64,${vipSample.toString('base64')}`;

const css = [
  'pdx-tokens.css',
  'pdx-unified-ui.css',
  'pdx-verified-badge.css',
  'pdx-customer-ui.css',
  'pdx-auth.css',
].map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

function headerHtml(dir, opts) {
  opts = opts || {};
  var name = opts.name || 'Alexandra Constantinopoulos';
  var level = opts.level
    ? '<span class="pdx-account-level-badge pdx-account-level-badge--compact pdx-account-level-badge--header">PAXDesign Level 03 — Diamond</span>'
    : '';
  var avatar = opts.avatar === false
    ? ''
    : '<span class="pdx-account-avatar pdx-account-avatar--header" style="width:32px;height:32px;flex:0 0 32px"><img class="pdx-account-avatar__img" src="' + sampleUri + '" width="32" height="32" alt="" /></span>';
  var legacy = opts.legacy
    ? '<span class="pdx-auth-account-label pdx-name-with-badge"><span class="pdx-account-name-text">' + name + '</span></span>'
    : '';
  return '<div id="pdx-auth-bar" class="pdx-cx-shell pdx-auth-bar--header" dir="' + dir + '">' +
    '<div class="pdx-auth-bar-inner">' +
      '<button type="button" class="pdx-auth-account-btn pdx-cx-btn pdx-cx-btn--ghost pdx-auth-header-btn">' +
        '<span class="pdx-auth-account-identity">' +
          '<span class="pdx-header-user-identity">' +
            avatar +
            '<span class="pdx-header-user-text">' +
              '<span class="pdx-header-user-name">' + name + '</span>' +
              level +
            '</span>' +
          '</span>' +
        '</span>' +
        legacy +
      '</button>' +
    '</div>' +
  '</div>';
}

const html = `<!DOCTYPE html><html dir="ltr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head><body>${headerHtml('ltr', { level: true, legacy: true })}${headerHtml('rtl', { level: true, avatar: false })}</body></html>`;

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

for (const [name, width, height] of [
  ['header-desktop', 1280, 800],
  ['header-mobile', 390, 844],
]) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 2 });
  await page.setContent(html, { waitUntil: 'networkidle0' });
  const metrics = await page.evaluate(() => {
    function check(bar) {
      var btn = bar.querySelector('.pdx-auth-account-btn');
      var names = bar.querySelectorAll('.pdx-header-user-name');
      var legacy = bar.querySelectorAll('.pdx-auth-account-label, .pdx-name-with-badge, .pdx-public-user-name');
      var avatar = bar.querySelector('.pdx-account-avatar--header');
      var level = bar.querySelector('.pdx-account-level-badge--header');
      var nameEl = names[0];
      if (!btn || !nameEl) return { ok: false, reason: 'missing nodes' };
      var legacyVisible = Array.from(legacy).some(function (el) {
        var style = getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
      });
      var nr = nameEl.getBoundingClientRect();
      var lr = level ? level.getBoundingClientRect() : null;
      var levelBelow = !lr || lr.top >= nr.bottom - 2;
      var singleName = names.length === 1;
      return {
        ok: singleName && !legacyVisible && levelBelow,
        singleName,
        legacyVisible,
        levelBelow,
        nameCount: names.length,
      };
    }
    return Array.from(document.querySelectorAll('#pdx-auth-bar')).map(check);
  });
  metrics.forEach((m, i) => {
    if (!m.ok) throw new Error(name + ' block ' + i + ' failed: ' + JSON.stringify(m));
  });
  console.log('OK: ' + name);
  await page.close();
}

await browser.close();
console.log('OK: main website header customer name tests passed');
