import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cssDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/css');

const css = [
  'pdx-auth-page.css',
  'pdx-account-app.css',
  'pdx-portal-apple.css',
].map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

const html = `<!DOCTYPE html>
<html class="pdx-auth-isolated">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>${css}</style>
</head>
<body class="pdx-auth-isolated pdx-auth-page-body pdx-account-dashboard-body">
<div id="pdx-account-app" class="pdx-account-app" style="display:block;width:100%;min-height:100vh;">
  <main class="pdx-account-main" id="pdx-account-main" style="padding:20px;">
    <div class="pdx-account-card">
      <div class="pdx-account-profile-identity">
        <span class="pdx-account-avatar pdx-account-avatar--profile-compact" style="width:64px;height:64px"><img class="pdx-account-avatar__img" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" width="64" height="64" /></span>
        <div class="pdx-account-profile-identity-text"><div class="pdx-account-profile-name">Test User</div></div>
      </div>
      <div class="pdx-account-profile-avatar-block pdx-account-profile-avatar-block--desktop">
        <label class="pdx-account-avatar__change">Change photo
          <input type="file" id="pdx-profile-avatar-input" class="pdx-account-avatar__file-input" accept="image/jpeg,image/png,image/webp" hidden />
        </label>
        <button type="button" class="pdx-account-avatar__remove" id="pdx-profile-avatar-remove">Remove photo</button>
      </div>
      <div class="pdx-account-profile-avatar-block pdx-account-profile-avatar-block--mobile">
        <div class="pdx-account-avatar-actions">
          <button type="button" class="pdx-account-avatar__change-btn" id="pdx-profile-avatar-change-mobile">Change photo</button>
          <button type="button" class="pdx-account-avatar__remove pdx-account-avatar__remove--mobile" id="pdx-profile-avatar-remove-mobile">Remove photo</button>
        </div>
      </div>
      <div class="pdx-account-avatar-picker">
        <div class="pdx-account-avatar-picker__grid">
          <button type="button" class="pdx-account-avatar-picker__item" data-avatar-preset="pax-01">A</button>
          <button type="button" class="pdx-account-avatar-picker__item is-selected" data-avatar-preset="pax-02">B</button>
        </div>
      </div>
    </div>
  </main>
</div>
<script>
document.getElementById('pdx-profile-avatar-change-mobile').addEventListener('click', function (e) {
  e.preventDefault();
  document.getElementById('pdx-profile-avatar-input').click();
});
window.__avatarPickerClicks = 0;
document.getElementById('pdx-profile-avatar-input').addEventListener('click', function () {
  window.__avatarPickerClicks += 1;
});
document.querySelectorAll('[data-avatar-preset]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    window.__lastPreset = btn.getAttribute('data-avatar-preset');
  });
});
</script>
</body>
</html>`;

const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox'],
});

async function runViewport(name, width, height, isMobile) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 2, isMobile: !!isMobile, hasTouch: !!isMobile });
  await page.setContent(html, { waitUntil: 'networkidle0' });

  const metrics = await page.evaluate(() => {
    const cs = (el) => (el ? getComputedStyle(el) : null);
    const desktop = document.querySelector('.pdx-account-profile-avatar-block--desktop');
    const mobile = document.querySelector('.pdx-account-profile-avatar-block--mobile');
    const changeLabel = document.querySelector('.pdx-account-avatar__change');
    const changeMobile = document.getElementById('pdx-profile-avatar-change-mobile');
    const removeMobile = document.getElementById('pdx-profile-avatar-remove-mobile');
    const input = document.getElementById('pdx-profile-avatar-input');
    return {
      desktopVisible: desktop ? cs(desktop).display !== 'none' : false,
      mobileVisible: mobile ? cs(mobile).display !== 'none' : false,
      changeLabelExists: !!changeLabel,
      changeMobileMinHeight: changeMobile ? parseFloat(cs(changeMobile).minHeight) || parseFloat(cs(changeMobile).height) : 0,
      removeMobileMinHeight: removeMobile ? parseFloat(cs(removeMobile).minHeight) || parseFloat(cs(removeMobile).height) : 0,
      inputExists: !!input,
      pickerItems: document.querySelectorAll('.pdx-account-avatar-picker__item').length,
    };
  });

  if (isMobile) {
    await page.evaluate(() => {
      document.getElementById('pdx-profile-avatar-change-mobile').click();
      document.querySelector('[data-avatar-preset="pax-01"]').click();
    });
  }

  const after = await page.evaluate(() => ({
    avatarPickerClicks: window.__avatarPickerClicks || 0,
    lastPreset: window.__lastPreset || null,
  }));

  await page.close();

  const isDesktop = width >= 901;
  const ok = isDesktop
    ? metrics.desktopVisible && !metrics.mobileVisible && metrics.changeLabelExists && metrics.inputExists
    : metrics.mobileVisible && !metrics.desktopVisible
      && metrics.changeMobileMinHeight >= 44
      && metrics.removeMobileMinHeight >= 44
      && after.avatarPickerClicks >= 1
      && after.lastPreset === 'pax-01';

  console.log(`[${name}]`, { ...metrics, ...after }, ok ? 'PASS' : 'FAIL');
  if (!ok) process.exitCode = 1;
}

await runViewport('desktop', 1280, 800, false);
await runViewport('mobile', 390, 844, true);
await browser.close();

if (!process.exitCode) {
  console.log('Account avatar mobile/desktop separation tests passed.');
}
