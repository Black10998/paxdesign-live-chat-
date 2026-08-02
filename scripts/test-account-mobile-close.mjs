import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cssDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/css');
const css = ['pdx-auth-page.css', 'pdx-account-app.css', 'pdx-portal-apple.css']
  .map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

const html = `<!DOCTYPE html><html class="pdx-auth-isolated"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style></head>
<body class="pdx-auth-isolated pdx-account-dashboard-body">
<div id="pdx-auth-isolated-shell">
  <div id="pdx-account-header" class="pdx-account-header"><button type="button" id="menu-btn" class="pdx-account-mobile-menu pdx-account-mobile-menu--in-header">Menu</button></div>
  <div id="pdx-account-app"><aside id="pdx-account-sidebar" class="pdx-account-sidebar"></aside><div id="pdx-account-main"></div></div>
</div>
<script>
(function(){
  var open = false;
  var sidebar = document.getElementById('pdx-account-sidebar');
  var backdrop = document.createElement('div');
  backdrop.className = 'pdx-account-mobile-backdrop pdx-account-mobile-backdrop--portal';
  var shell = document.getElementById('pdx-auth-isolated-shell');
  function closeNav(){
    open = false;
    document.body.classList.remove('pdx-account-mobile-nav-open');
    sidebar.setAttribute('aria-hidden', 'true');
  }
  function openNav(){
    open = true;
    sidebar.classList.add('pdx-account-sidebar--mobile-overlay');
    shell.appendChild(backdrop);
    shell.appendChild(sidebar);
    document.body.classList.add('pdx-account-mobile-nav-open');
    sidebar.setAttribute('aria-hidden', 'false');
  }
  sidebar.innerHTML = '<div class="pdx-account-sidebar-mobile-head"><span>Nav</span><button type="button" class="pdx-account-sidebar-close">X</button></div>';
  sidebar.addEventListener('click', function(e){
    if (e.target.closest('.pdx-account-sidebar-close')) { e.preventDefault(); closeNav(); }
  });
  backdrop.addEventListener('click', closeNav);
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && open) closeNav(); });
  document.getElementById('menu-btn').addEventListener('click', function(){ open ? closeNav() : openNav(); });
})();
</script></body></html>`;

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 390, height: 844 });
await page.setContent(html, { waitUntil: 'domcontentloaded' });

await page.click('#menu-btn');
await new Promise((r) => setTimeout(r, 350));
let state = await page.evaluate(() => ({
  open: document.body.classList.contains('pdx-account-mobile-nav-open'),
  sidebarZ: getComputedStyle(document.getElementById('pdx-account-sidebar')).zIndex,
}));
if (!state.open) { console.error('FAIL: menu should open'); process.exit(1); }

await page.click('.pdx-account-sidebar-close');
await new Promise((r) => setTimeout(r, 350));
state = await page.evaluate(() => document.body.classList.contains('pdx-account-mobile-nav-open'));
if (state) { console.error('FAIL: close button should close menu'); process.exit(1); }

await page.click('#menu-btn');
await new Promise((r) => setTimeout(r, 200));
await page.mouse.click(360, 400);
await new Promise((r) => setTimeout(r, 350));
state = await page.evaluate(() => document.body.classList.contains('pdx-account-mobile-nav-open'));
if (state) { console.error('FAIL: backdrop should close menu'); process.exit(1); }

await page.click('#menu-btn');
await new Promise((r) => setTimeout(r, 200));
await page.keyboard.press('Escape');
await new Promise((r) => setTimeout(r, 350));
state = await page.evaluate(() => document.body.classList.contains('pdx-account-mobile-nav-open'));
if (state) { console.error('FAIL: Escape should close menu'); process.exit(1); }

await browser.close();
console.log('Mobile menu close checks passed.');
