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
  console.error('Expected 50 SVG avatars, found', svgFiles.length);
  process.exit(1);
}

const presets = svgFiles.map((file, index) => ({
  id: file.replace('.svg', ''),
  label: `Avatar ${index + 1}`,
  url: `file://${join(avatarDir, file)}`,
}));

const pickerHtml = presets.map((preset, index) => {
  const selected = index === 4 ? ' is-selected' : '';
  return `<button type="button" class="pdx-account-avatar-picker__item${selected}" data-avatar-preset="${preset.id}" aria-selected="${selected ? 'true' : 'false'}"><img src="${preset.url}" alt="" width="48" height="48" /></button>`;
}).join('');

const fallbackUrl = presets[4].url;
const brokenUrl = 'https://example.invalid/broken-avatar.jpg';

const html = `<!DOCTYPE html>
<html class="pdx-auth-isolated">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>${css}</style>
</head>
<body class="pdx-auth-isolated pdx-auth-page-body pdx-account-dashboard-body">
<div id="pdx-account-app" class="pdx-account-app">
  <main class="pdx-account-main">
    <div class="pdx-account-card">
      <div class="pdx-account-profile-identity">
        <span class="pdx-account-avatar pdx-account-avatar--profile-compact" style="width:64px;height:64px">
          <img id="broken-avatar" class="pdx-account-avatar__img" src="${brokenUrl}" data-avatar-fallback="${fallbackUrl}" width="64" height="64" />
        </span>
      </div>
      <div class="pdx-account-avatar-picker">
        <div class="pdx-account-avatar-picker__title">Choose a PAXDesign avatar</div>
        <div class="pdx-account-avatar-picker__grid">${pickerHtml}</div>
      </div>
    </div>
  </main>
</div>
<script>
window.__pdxAvatarFallback = function(img) {
  if (!img || img.dataset.pdxAvatarFailed === '1') return;
  var fallback = img.getAttribute('data-avatar-fallback');
  if (!fallback) return;
  img.dataset.pdxAvatarFailed = '1';
  img.src = fallback;
};
document.getElementById('broken-avatar').addEventListener('error', function() {
  window.__pdxAvatarFallback(this);
});
</script>
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
  await page.waitForFunction(() => {
    const img = document.getElementById('broken-avatar');
    return img && img.dataset.pdxAvatarFailed === '1' && img.src.startsWith('file:');
  }, { timeout: 5000 });

  const metrics = await page.evaluate(() => {
    const img = document.getElementById('broken-avatar');
    const avatar = img.closest('.pdx-account-avatar');
    const grid = document.querySelector('.pdx-account-avatar-picker__grid');
    const items = document.querySelectorAll('.pdx-account-avatar-picker__item');
    const avatarRect = avatar.getBoundingClientRect();
    const imgRect = img.getBoundingClientRect();
    const gridRect = grid.getBoundingClientRect();
    return {
      fallbackApplied: img.dataset.pdxAvatarFailed === '1',
      avatarSize: { w: avatarRect.width, h: avatarRect.height },
      imgSize: { w: imgRect.width, h: imgRect.height },
      pickerCount: items.length,
      gridWidth: gridRect.width,
      selectedCount: document.querySelectorAll('.pdx-account-avatar-picker__item.is-selected').length,
    };
  });

  await page.close();

  const ok =
    metrics.fallbackApplied &&
    Math.abs(metrics.avatarSize.w - 64) < 2 &&
    Math.abs(metrics.avatarSize.h - 64) < 2 &&
    metrics.pickerCount === 50 &&
    metrics.selectedCount === 1;

  console.log(`[${name}]`, metrics, ok ? 'PASS' : 'FAIL');
  if (!ok) process.exitCode = 1;
}

await runViewport('desktop', 1280, 800);
await runViewport('mobile', 390, 844);
await browser.close();

if (!process.exitCode) {
  console.log('Avatar preset fallback/layout tests passed.');
}
