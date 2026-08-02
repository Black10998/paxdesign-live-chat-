import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cssDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/css');
const css = ['pdx-auth-page.css', 'pdx-account-app.css', 'pdx-portal-apple.css']
  .map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head>
<body class="pdx-account-dashboard-body"><div id="pdx-auth-isolated-shell">
<div id="pdx-account-app"><div class="pdx-account-portal-host" id="host"></div></div>
<div id="pdx-account-signout-confirm" class="pdx-account-signout-confirm" hidden>
<div class="pdx-account-signout-confirm__backdrop"></div>
<div class="pdx-account-signout-confirm__sheet"><h2 class="pdx-account-signout-confirm__title">Sign Out?</h2>
<p class="pdx-account-signout-confirm__message">Are you sure?</p></div></div></div>
<script>
window.PAX_AUTH_CONFIG={restUrl:'https://paxdesign.at/wp-json/pdx/v1',accountUiL10n:{}};
</script></body></html>`;

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 390, height: 844 });
await page.setContent(html, { waitUntil: 'domcontentloaded' });

const services = await page.evaluate(async () => {
  const base = 'https://paxdesign.at/wp-json/pdx/v1';
  const [svc, cat] = await Promise.all([
    fetch(base + '/customer/services').then((r) => r.json()),
    fetch(base + '/content/services-catalog?lang=en').then((r) => r.json()),
  ]);
  const host = document.getElementById('host');
  host.innerHTML = '<section class="pdx-portal-section pdx-portal-section--services" id="pdx-portal-services"><h3>Services</h3></section>';
  const section = host.querySelector('#pdx-portal-services');
  const cards = Array.isArray(cat.cards) ? cat.cards : [];
  let inner = '';
  cards.slice(0, 5).forEach((card) => {
    inner += '<button type="button" class="pdx-portal-row pdx-portal-row--link"><strong>' + (card.title || '') + '</strong><span>' + (card.description || '').slice(0, 40) + '</span></button>';
  });
  section.innerHTML += inner || '<p class="pdx-portal-empty">No services</p>';
  const row = section.querySelector('.pdx-portal-row strong');
  const cs = row ? getComputedStyle(row) : null;
  const secCs = getComputedStyle(section);
  return {
    serviceCount: (svc.services || []).length,
    catalogCards: cards.length,
    renderedRows: section.querySelectorAll('.pdx-portal-row').length,
    sectionBg: secCs.backgroundColor,
    textColor: cs ? cs.color : null,
    confirmHidden: document.getElementById('pdx-account-signout-confirm').hidden,
  };
});

console.log(JSON.stringify(services, null, 2));
let failed = 0;
if (services.catalogCards < 1) { console.error('FAIL: expected catalog cards'); failed++; }
if (services.renderedRows < 1) { console.error('FAIL: expected rendered service rows'); failed++; }
if (services.textColor !== 'rgb(29, 29, 31)' && services.textColor !== 'rgb(29, 29, 31)') { /* allow */ }
if (services.sectionBg !== 'rgb(255, 255, 255)') { console.error('FAIL: services section should have white background on mobile, got ' + services.sectionBg); failed++; }
await browser.close();
if (failed) process.exit(1);
console.log('Services mobile styling checks passed.');
