import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const pluginRoot = join(__dirname, '../paxdesign-booking');
const cssDir = join(pluginRoot, 'assets/customer-auth/css');
const vipSample = readFileSync(join(pluginRoot, 'assets/customer-auth/images/avatars-vip/pax-vip-01.gif'));
const sampleUri = `data:image/gif;base64,${vipSample.toString('base64')}`;

const css = [
  'pdx-auth-page.css',
  'pdx-account-app.css',
  'pdx-portal-apple.css',
].map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

function vipTile(id, locked, granted) {
  return `<div class="pdx-account-admin-vip-tile">
    <button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--vip pdx-admin-assign-avatar${locked ? ' pdx-account-avatar-picker__item--locked' : ''}" data-avatar-preset="${id}"${locked ? ' data-avatar-locked="1" disabled' : ''}>
      <img src="${sampleUri}" width="48" height="48" />
      ${locked ? '<span class="pdx-account-avatar-picker__lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V11a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5z"/></svg></span>' : ''}
    </button>
    <div class="pdx-account-admin-vip-tile__meta">
      <span class="pdx-account-admin-vip-tile__label">${id}</span>
      ${granted ? '<button type="button" class="pdx-portal-btn pdx-portal-btn--ghost pdx-admin-revoke-vip">Revoke</button>' : '<button type="button" class="pdx-portal-btn pdx-portal-btn--secondary pdx-admin-grant-vip">Grant</button>'}
    </div>
  </div>`;
}

const masterAdminHtml = `<!DOCTYPE html><html class="pdx-auth-isolated"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head>
<body class="pdx-auth-isolated pdx-account-dashboard-body"><div class="pdx-account-app"><main class="pdx-account-main" style="padding:20px">
<div class="pdx-account-avatar-picker"><div class="pdx-account-avatar-picker__subtitle">VIP preview (master admin)</div>
<div class="pdx-account-avatar-picker__grid pdx-account-avatar-picker__grid--vip">
${Array.from({ length: 10 }, (_, i) => {
  const id = `pax-vip-${String(i + 1).padStart(2, '0')}`;
  return `<button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--vip" data-avatar-preset="${id}"><img src="${sampleUri}" width="48" height="48" /></button>`;
}).join('')}
</div></div></main></div></body></html>`;

const adminEditorHtml = `<!DOCTYPE html><html class="pdx-auth-isolated"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head>
<body class="pdx-auth-isolated pdx-account-dashboard-body"><div class="pdx-account-app"><main class="pdx-account-main" style="padding:20px"><div class="pdx-account-admin-editor">
<div class="pdx-account-admin-preview"><div class="pdx-account-admin-preview__label">Customer account preview</div><div class="pdx-account-admin-preview__card"><div class="pdx-account-profile-identity"><span class="pdx-account-avatar pdx-account-avatar--profile-compact" style="width:64px;height:64px"><img class="pdx-account-avatar__img" src="${sampleUri}" width="64" height="64" /></span><div class="pdx-account-profile-identity-text"><div class="pdx-account-profile-name">Sample Customer</div><span class="pdx-account-level-badge">PAXDesign Level 01 — Gold</span></div></div></div></div>
<div class="pdx-account-card"><div class="pdx-account-admin-section-title">Avatar assignment</div><div class="pdx-account-avatar-picker__grid pdx-account-admin-avatar-grid">${vipTile('pax-vip-01', true, false)}${vipTile('pax-vip-02', false, true)}</div></div>
</div></main></div></body></html>`;

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

for (const [name, html, checks] of [
  ['master-admin-vip-unlocked', masterAdminHtml, async (page) => {
    const m = await page.evaluate(() => ({
      vip: document.querySelectorAll('.pdx-account-avatar-picker__item--vip').length,
      locked: document.querySelectorAll('.pdx-account-avatar-picker__item--locked').length,
      locks: document.querySelectorAll('.pdx-account-avatar-picker__lock').length,
    }));
    if (m.vip !== 10) throw new Error(`Expected 10 VIP tiles, got ${m.vip}`);
    if (m.locked !== 0) throw new Error(`Master admin preview should have 0 locked VIP tiles, got ${m.locked}`);
    if (m.locks !== 0) throw new Error(`Master admin preview should have 0 lock overlays, got ${m.locks}`);
  }],
  ['admin-customer-editor-desktop', adminEditorHtml, async (page) => {
    const m = await page.evaluate(() => ({
      preview: !!document.querySelector('.pdx-account-admin-preview__card'),
      grant: document.querySelectorAll('.pdx-admin-grant-vip').length,
      revoke: document.querySelectorAll('.pdx-admin-revoke-vip').length,
      locked: document.querySelectorAll('.pdx-account-avatar-picker__item--locked').length,
      grid: !!document.querySelector('.pdx-account-admin-avatar-grid'),
    }));
    if (!m.preview) throw new Error('Missing customer preview card');
    if (!m.grid) throw new Error('Missing admin avatar grid');
    if (m.grant !== 1 || m.revoke !== 1) throw new Error('Expected grant/revoke controls on VIP tiles');
    if (m.locked !== 1) throw new Error('Expected one locked VIP tile for normal customer management');
  }],
  ['admin-customer-editor-mobile', adminEditorHtml, async (page) => {
    await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
    const m = await page.evaluate(() => ({
      preview: !!document.querySelector('.pdx-account-admin-preview'),
      toolbar: !!document.querySelector('.pdx-account-admin-editor'),
    }));
    if (!m.preview || !m.toolbar) throw new Error('Mobile admin editor layout missing sections');
  }],
]) {
  const page = await browser.newPage();
  if (name !== 'admin-customer-editor-mobile') {
    await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  }
  await page.setContent(html, { waitUntil: 'networkidle0' });
  await checks(page);
  console.log(`OK: ${name}`);
  await page.close();
}

await browser.close();
console.log('OK: master admin customer editor visual tests passed');
