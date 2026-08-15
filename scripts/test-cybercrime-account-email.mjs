import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const themeRoot = join(__dirname, '../navein');
const css = readFileSync(join(themeRoot, 'assets/css/apple-cybercrime-support.css'), 'utf8');

function buildHtml(loggedIn, email) {
  const lockedAttrs = loggedIn
    ? 'readonly aria-readonly="true" data-account-email-locked="1" value="' + email + '"'
    : 'value=""';
  const lockedClass = loggedIn ? ' pax-ccs-portal__field--account-email-locked' : '';
  const verifiedNote = loggedIn
    ? '<p class="pax-ccs-portal__account-email-verified"><span class="pax-ccs-portal__account-email-verified-icon">✓</span><span>Verified account email</span></p>'
    : '';

  return `<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head>
<body>
<article class="pax-ccs-portal" data-ccs-lang="en">
<form id="pax-ccs-intake-form">
<section class="pax-ccs-portal__step is-active" data-step="1">
<div class="pax-ccs-portal__field pax-ccs-portal__field--full${lockedClass}" id="pax-ccs-email-field-wrap">
<label for="pax-ccs-email">Email address</label>
${verifiedNote}
<input type="email" id="pax-ccs-email" name="email" required ${lockedAttrs} />
</div>
</section>
</form>
</article>
<script>
window.paxCybercrimeIntake = ${JSON.stringify({
    isLoggedIn: loggedIn,
    accountEmail: loggedIn ? email : '',
    emailLocked: loggedIn,
    i18n: { accountEmail: { verifiedNote: { en: 'Verified account email' } } },
  })};
${readFileSync(join(themeRoot, 'assets/js/apple-cybercrime-support.js'), 'utf8')}
</script>
</body></html>`;
}

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

async function runCase(name, loggedIn) {
  const email = 'customer@paxdesign.at';
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 800 });
  await page.setContent(buildHtml(loggedIn, email), { waitUntil: 'networkidle0' });

  const metrics = await page.evaluate(() => {
    const input = document.getElementById('pax-ccs-email');
    const wrap = document.getElementById('pax-ccs-email-field-wrap');
    return {
      value: input ? input.value : '',
      readOnly: input ? input.readOnly : false,
      lockedAttr: input ? input.getAttribute('data-account-email-locked') : null,
      lockedClass: wrap ? wrap.classList.contains('pax-ccs-portal__field--account-email-locked') : false,
      verifiedVisible: !!document.querySelector('.pax-ccs-portal__account-email-verified'),
    };
  });

  await page.close();
  const ok = loggedIn
    ? metrics.value === email && metrics.readOnly && metrics.lockedAttr === '1' && metrics.lockedClass && metrics.verifiedVisible
    : metrics.value === '' && !metrics.readOnly && !metrics.lockedClass && !metrics.verifiedVisible;

  console.log(`[${name}]`, metrics, ok ? 'PASS' : 'FAIL');
  if (!ok) process.exitCode = 1;
}

await runCase('logged-out-desktop', false);
await runCase('logged-in-desktop', true);
await browser.newPage().then(async (page) => {
  await page.setViewport({ width: 390, height: 844 });
  await page.setContent(buildHtml(true, 'customer@paxdesign.at'), { waitUntil: 'networkidle0' });
  const mobile = await page.evaluate(() => ({
    readOnly: document.getElementById('pax-ccs-email').readOnly,
    verifiedVisible: !!document.querySelector('.pax-ccs-portal__account-email-verified'),
  }));
  await page.close();
  const ok = mobile.readOnly && mobile.verifiedVisible;
  console.log('[logged-in-mobile]', mobile, ok ? 'PASS' : 'FAIL');
  if (!ok) process.exitCode = 1;
});
await browser.close();
if (!process.exitCode) console.log('Cybercrime account email tests passed.');
