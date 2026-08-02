import puppeteer from 'puppeteer';
import { readFileSync, readdirSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const pluginRoot = join(__dirname, '../paxdesign-booking');
const cssDir = join(pluginRoot, 'assets/customer-auth/css');
const avatarDir = join(pluginRoot, 'assets/customer-auth/images/avatars');

const css = [
  'pdx-auth-page.css',
  'pdx-account-app.css',
  'pdx-portal-apple.css',
].map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

const svgFiles = readdirSync(avatarDir).filter((f) => f.endsWith('.svg')).sort();
if (svgFiles.length !== 50) {
  console.error('Expected 50 face SVG avatars, found', svgFiles.length);
  process.exit(1);
}

const sample = readFileSync(join(avatarDir, 'pax-01.svg'), 'utf8');
if (!sample.includes('ellipse') || sample.includes('Design Studio')) {
  console.error('SVG avatars do not look like face portraits');
  process.exit(1);
}

const faceDataUri = `data:image/svg+xml,${encodeURIComponent(sample)}`;

const presets = [{ id: 'pax-none', label: 'No profile picture', url: '', type: 'none' }].concat(
  svgFiles.map((file, index) => ({
    id: file.replace('.svg', ''),
    label: `Portrait ${index + 1}`,
    url: faceDataUri,
    type: 'portrait',
  }))
);

const portraitButtons = presets.filter((p) => p.type !== 'none').slice(0, 12).map((preset, index) => {
  const selected = index === 2 ? ' is-selected' : '';
  return `<button type="button" class="pdx-account-avatar-picker__item${selected}" data-avatar-preset="${preset.id}" aria-selected="${selected ? 'true' : 'false'}"><img src="${preset.url}" alt="" width="48" height="48" /></button>`;
}).join('');

const noneBtn = `<button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--none is-selected" data-avatar-preset="pax-none" aria-selected="true"><span class="pdx-account-avatar-picker__none-mark"></span><span class="pdx-account-avatar-picker__none-text">No profile picture</span></button>`;
const faceUrl = presets[3].url;

const html = `<!DOCTYPE html>
<html class="pdx-auth-isolated">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>${css}</style>
</head>
<body class="pdx-auth-isolated pdx-auth-page-body pdx-account-dashboard-body">
<div id="pdx-account-app" class="pdx-account-app" style="display:block;width:100%;min-height:100vh;">
  <main class="pdx-account-main" style="padding:20px;min-height:100vh;box-sizing:border-box;">
    <div class="pdx-account-card" style="padding:20px;max-width:640px;">
      <div class="pdx-account-profile-identity" id="identity-with-face">
        <span class="pdx-account-avatar pdx-account-avatar--profile-compact" style="width:64px;height:64px;max-width:64px;max-height:64px;flex:0 0 64px"><img class="pdx-account-avatar__img" src="${faceUrl}" width="64" height="64" /></span>
        <div class="pdx-account-profile-identity-text"><div class="pdx-account-profile-name">With portrait</div></div>
      </div>
      <div class="pdx-account-profile-identity" id="identity-none" style="margin-top:16px;">
        <div class="pdx-account-profile-identity-text"><div class="pdx-account-profile-name">No profile picture</div></div>
      </div>
      <div class="pdx-account-avatar-picker">
        <div class="pdx-account-avatar-picker__title">Choose a PAXDesign avatar</div>
        <div class="pdx-account-avatar-picker__grid">${noneBtn}${portraitButtons}</div>
      </div>
    </div>
  </main>
</div>
</body>
</html>`;

const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--allow-file-access-from-files'],
});

async function runViewport(name, width, height) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 2 });
  await page.setContent(html, { waitUntil: 'networkidle0' });

  const metrics = await page.evaluate(() => {
    const faceImg = document.querySelector('#identity-with-face img');
    const faceWrap = document.querySelector('#identity-with-face .pdx-account-avatar');
    const noneIdentity = document.querySelector('#identity-none');
    const noneBtn = document.querySelector('.pdx-account-avatar-picker__item--none');
    const portraits = document.querySelectorAll('.pdx-account-avatar-picker__item:not(.pdx-account-avatar-picker__item--none)');
    const faceRect = faceWrap ? faceWrap.getBoundingClientRect() : { width: 0, height: 0 };
    const noneRect = noneBtn ? noneBtn.getBoundingClientRect() : { width: 0, height: 0 };
    return {
      faceSize: { w: faceRect.width, h: faceRect.height },
      noneBtnVisible: !!noneBtn && noneRect.width > 40,
      noneSelected: noneBtn ? noneBtn.classList.contains('is-selected') : false,
      portraitCount: portraits.length,
      identityWithoutAvatar: noneIdentity ? noneIdentity.querySelectorAll('.pdx-account-avatar').length === 0 : false,
      faceLoaded: faceImg ? faceImg.complete && faceImg.naturalWidth > 0 : false,
    };
  });

  await page.close();

  const ok =
    metrics.faceLoaded &&
    Math.abs(metrics.faceSize.w - 64) < 2 &&
    metrics.noneBtnVisible &&
    metrics.noneSelected &&
    metrics.portraitCount === 12 &&
    metrics.identityWithoutAvatar;

  console.log(`[${name}]`, metrics, ok ? 'PASS' : 'FAIL');
  if (!ok) process.exitCode = 1;
}

await runViewport('desktop', 1280, 800);
await runViewport('mobile', 390, 844);
await browser.close();

if (!process.exitCode) {
  console.log('Face portrait avatar tests passed.');
}
