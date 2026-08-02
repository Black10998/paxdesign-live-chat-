import puppeteer from 'puppeteer';
import { readFileSync, readdirSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const pluginRoot = join(__dirname, '../paxdesign-booking');
const cssDir = join(pluginRoot, 'assets/customer-auth/css');
const vipDir = join(pluginRoot, 'assets/customer-auth/images/avatars-vip');

const css = [
  'pdx-auth-page.css',
  'pdx-account-app.css',
  'pdx-portal-apple.css',
].map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

const vipFiles = readdirSync(vipDir).filter((f) => f.endsWith('.gif')).sort();
if (vipFiles.length !== 10) {
  console.error('Expected 10 VIP GIF avatars, found', vipFiles.length);
  process.exit(1);
}

for (const file of vipFiles) {
  const buf = readFileSync(join(vipDir, file));
  if (buf[0] !== 0x47 || buf[1] !== 0x49) {
    console.error(`${file} is not a valid GIF`);
    process.exit(1);
  }
}

const sampleUri = `data:image/gif;base64,${readFileSync(join(vipDir, 'pax-vip-01.gif')).toString('base64')}`;
const vipPresets = vipFiles.map((file, index) => ({
  id: file.replace('.gif', ''),
  label: `VIP avatar ${index + 1}`,
  url: sampleUri,
  type: 'vip',
  locked: index !== 2,
}));

function renderPickerHtml(vipList, currentPreset) {
  const noneBtn = `<button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--none" data-avatar-preset="pax-none"><span class="pdx-account-avatar-picker__none-mark"></span><span class="pdx-account-avatar-picker__none-text">No profile picture</span></button>`;
  const standard = `<button type="button" class="pdx-account-avatar-picker__item" data-avatar-preset="pax-01"><img src="${sampleUri}" width="48" height="48" /></button>`;
  const vipHtml = vipList.map((preset) => {
    const locked = !!preset.locked;
    const selected = !locked && preset.id === currentPreset;
    return `<button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--vip${locked ? ' pdx-account-avatar-picker__item--locked' : ''}${selected ? ' is-selected' : ''}" data-avatar-preset="${preset.id}"${locked ? ' data-avatar-locked="1" disabled aria-disabled="true"' : ''}>` +
      `<img src="${preset.url}" alt="" width="48" height="48" />` +
      (locked ? '<span class="pdx-account-avatar-picker__lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V11a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5z"/></svg></span>' : '') +
      '</button>';
  }).join('');
  return `<!DOCTYPE html><html class="pdx-auth-isolated"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head>
<body class="pdx-auth-isolated pdx-account-dashboard-body"><div class="pdx-account-app"><main class="pdx-account-main" style="padding:20px"><div class="pdx-account-card" style="padding:20px">
<div class="pdx-account-avatar-picker"><div class="pdx-account-avatar-picker__title">Choose avatar</div><div class="pdx-account-avatar-picker__grid">${noneBtn}${standard}</div>
<div class="pdx-account-avatar-picker__subtitle">Exclusive VIP avatars</div><div class="pdx-account-avatar-picker__grid pdx-account-avatar-picker__grid--vip">${vipHtml}</div></div>
</div></main></div></body></html>`;
}

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

async function runViewport(name, width, height) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 2 });
  await page.setContent(renderPickerHtml(vipPresets, 'pax-vip-03'), { waitUntil: 'networkidle0' });
  const metrics = await page.evaluate(() => {
    const locked = document.querySelectorAll('.pdx-account-avatar-picker__item--locked');
    const unlocked = document.querySelectorAll('.pdx-account-avatar-picker__item--vip:not(.pdx-account-avatar-picker__item--locked)');
    const lockIcons = document.querySelectorAll('.pdx-account-avatar-picker__lock');
    const selected = document.querySelector('.pdx-account-avatar-picker__item--vip.is-selected');
    const lockedStyle = locked[0] ? getComputedStyle(locked[0]) : null;
    return {
      vipCount: document.querySelectorAll('.pdx-account-avatar-picker__item--vip').length,
      lockedCount: locked.length,
      unlockedCount: unlocked.length,
      lockIconCount: lockIcons.length,
      selectedId: selected ? selected.getAttribute('data-avatar-preset') : '',
      lockedDisabled: locked[0] ? locked[0].disabled : false,
      lockedPointerEvents: lockedStyle ? lockedStyle.pointerEvents : '',
      lockedFilter: lockedStyle ? lockedStyle.filter : '',
    };
  });
  await page.close();
  const ok = metrics.vipCount === 10 && metrics.lockedCount === 9 && metrics.unlockedCount === 1 &&
    metrics.lockIconCount === 9 && metrics.selectedId === 'pax-vip-03' && metrics.lockedDisabled &&
    metrics.lockedPointerEvents === 'none' && metrics.lockedFilter.includes('grayscale');
  console.log(`[${name}]`, metrics, ok ? 'PASS' : 'FAIL');
  if (!ok) process.exitCode = 1;
}

await runViewport('desktop', 1280, 800);
await runViewport('mobile', 390, 844);
await browser.close();

if (!process.exitCode) {
  console.log('VIP avatar picker tests passed.');
}
