import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const themeRoot = join(__dirname, '../navein');
const css = readFileSync(join(themeRoot, 'assets/css/apple-cybercrime-support.css'), 'utf8');
const js = readFileSync(join(themeRoot, 'assets/js/apple-cybercrime-support.js'), 'utf8');
const phpPath = join(themeRoot, 'inc/cybercrime-countries.php');
const phpSrc = readFileSync(phpPath, 'utf8');

const countryCount = (phpSrc.match(/'code'/g) || []).length;
if (countryCount < 240) {
  console.error(`Expected >=240 countries, got ${countryCount}`);
  process.exit(1);
}
console.log(`Country data file: ${countryCount} countries OK`);

const countries = [];
const re = /array\(\s*'code'\s*=>\s*'([^']+)',\s*'dial'\s*=>\s*'([^']+)',\s*'flag'\s*=>\s*'([^']+)',\s*'name'\s*=>\s*array\(\s*'en'\s*=>\s*'((?:\\'|[^'])*)',\s*'de'\s*=>\s*'((?:\\'|[^'])*)',\s*'ar'\s*=>\s*'((?:\\'|[^'])*)'\s*\)\s*\)/g;
let match;
while ((match = re.exec(phpSrc))) {
  countries.push({
    code: match[1],
    dial: match[2],
    flag: match[3],
    name: {
      en: match[4].replace(/\\'/g, "'"),
      de: match[5].replace(/\\'/g, "'"),
      ar: match[6].replace(/\\'/g, "'"),
    },
  });
}
if (countries.length < 240) {
  console.error('Parsed country JSON too small:', countries.length);
  process.exit(1);
}

function pageHtml(dir) {
  const config = JSON.stringify({
    defaultPhoneCountry: 'DE',
    phonePopular: ['AT', 'DE', 'CH'],
    countries,
  });
  return `<!DOCTYPE html>
<html dir="${dir}" lang="${dir === 'rtl' ? 'ar' : 'en'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head>
<body>
<article class="pax-ccs-portal" data-ccs-lang="${dir === 'rtl' ? 'ar' : 'en'}">
<form id="pax-ccs-intake-form"><section class="pax-ccs-portal__step is-active" data-step="1">
<div class="pax-ccs-portal__field pax-ccs-portal__field--full"><label for="pax-ccs-phone-local">Phone</label>
<div class="pax-ccs-portal__phone-unified" id="pax-ccs-phone-wrap">
<input type="tel" id="pax-ccs-phone-local" name="phone_local" required />
<div class="pax-ccs-portal__phone-dial" id="pax-ccs-phone-dial">
<button type="button" class="pax-ccs-portal__phone-dial-trigger" id="pax-ccs-phone-dial-trigger" aria-expanded="false"><span class="pax-ccs-portal__phone-dial-flag" id="pax-ccs-phone-dial-flag"></span><span class="pax-ccs-portal__phone-dial-code" id="pax-ccs-phone-dial-code"></span><span class="pax-ccs-portal__phone-dial-chevron"></span></button>
<div class="pax-ccs-portal__phone-dial-panel" id="pax-ccs-phone-dial-panel" hidden><input type="search" id="pax-ccs-phone-dial-search" class="pax-ccs-portal__phone-dial-search" /><ul id="pax-ccs-phone-dial-list" class="pax-ccs-portal__phone-dial-list" role="listbox"></ul></div>
</div>
<input type="hidden" id="pax-ccs-phone-code" name="phone_country_code"><input type="hidden" id="pax-ccs-phone-country" name="phone_country"><input type="hidden" id="pax-ccs-phone" name="phone"></div></div>
<div class="pax-ccs-portal__field pax-ccs-portal__field--full"><label for="pax-ccs-country-search">Country</label>
<div class="pax-ccs-portal__country-picker" id="pax-ccs-country-picker"><input type="search" id="pax-ccs-country-search" class="pax-ccs-portal__country-search" aria-expanded="false" aria-controls="pax-ccs-country-list" /><input type="hidden" id="pax-ccs-country" name="country" required value=""><ul id="pax-ccs-country-list" class="pax-ccs-portal__country-list" role="listbox" hidden></ul></div></div>
</section></form></article>
<script>window.paxCybercrimeIntake=${config};${js}</script></body></html>`;
}

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

async function runCase(name, dir, width, height) {
  const page = await browser.newPage();
  page.on('pageerror', (err) => console.error(`[${name}] pageerror`, err.message));
  await page.setViewport({ width, height });
  await page.setContent(pageHtml(dir), { waitUntil: 'networkidle0' });

  const triggerExists = await page.$('#pax-ccs-phone-dial-trigger');
  if (!triggerExists) {
    console.log(`[${name}] FAIL — phone dial trigger missing`);
    process.exitCode = 1;
    await page.close();
    return;
  }

  await page.evaluate(() => document.getElementById('pax-ccs-phone-dial-trigger').click());
  await page.waitForFunction(() => document.querySelectorAll('#pax-ccs-phone-dial-list li').length > 0, { timeout: 5000 });
  const dialCount = await page.$$eval('#pax-ccs-phone-dial-list li', (els) => els.length);

  await page.evaluate(() => {
    const search = document.getElementById('pax-ccs-phone-dial-search');
    search.value = '+81';
    search.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await page.waitForFunction(() => {
    const first = document.querySelector('#pax-ccs-phone-dial-list li[data-country-code="JP"]');
    return !!first;
  }, { timeout: 5000 });
  await page.evaluate(() => document.querySelector('#pax-ccs-phone-dial-list li[data-country-code="JP"]').click());

  const phoneState = await page.evaluate(() => ({
    dial: document.getElementById('pax-ccs-phone-code').value,
    country: document.getElementById('pax-ccs-phone-country').value,
    unified: !!document.querySelector('.pax-ccs-portal__phone-unified'),
    selectCount: document.querySelectorAll('#pax-ccs-phone-wrap select').length,
    defaultDe: document.getElementById('pax-ccs-phone-country').value === 'DE' || document.getElementById('pax-ccs-phone-country').value === 'JP',
  }));

  await page.evaluate(() => {
    const input = document.getElementById('pax-ccs-phone-local');
    input.value = '6641234567';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  const fullPhone = await page.$eval('#pax-ccs-phone', (el) => el.value);

  await page.evaluate(() => {
    const search = document.getElementById('pax-ccs-country-search');
    search.focus();
    search.dispatchEvent(new Event('focus', { bubbles: true }));
  });
  await page.waitForFunction(() => document.querySelectorAll('#pax-ccs-country-list li').length > 0, { timeout: 5000 });
  const countryCountUi = await page.$$eval('#pax-ccs-country-list li', (els) => els.length);
  await page.evaluate(() => {
    const search = document.getElementById('pax-ccs-country-search');
    search.value = 'ZW';
    search.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await page.waitForFunction(() => document.getElementById('pax-ccs-country').value === 'ZW' || /ZW|Zimbabwe|زيمبابوي|Simbabwe/i.test(document.querySelector('#pax-ccs-country-list li')?.textContent || ''), { timeout: 5000 });
  await page.evaluate(() => {
    const item = document.querySelector('#pax-ccs-country-list li[data-country-code="ZW"]') || document.querySelector('#pax-ccs-country-list li');
    if (item) item.click();
  });
  const residence = await page.evaluate(() => ({
    code: document.getElementById('pax-ccs-country').value,
    label: document.getElementById('pax-ccs-country-search').value,
  }));

  await page.close();
  const ok = dialCount >= 240
    && phoneState.unified
    && phoneState.selectCount === 0
    && phoneState.country === 'JP'
    && phoneState.dial === '+81'
    && fullPhone.includes('+81')
    && countryCountUi >= 240
    && residence.code === 'ZW';

  console.log(`[${name}]`, { dialCount, countryCountUi, phoneState, fullPhone, residence }, ok ? 'PASS' : 'FAIL');
  if (!ok) process.exitCode = 1;
}

await runCase('desktop-ltr', 'ltr', 1280, 800);
await runCase('mobile-ltr', 'ltr', 390, 844);
await runCase('mobile-rtl-ar', 'rtl', 390, 844);
await browser.close();
if (!process.exitCode) console.log('Cybercrime country/phone tests passed.');
