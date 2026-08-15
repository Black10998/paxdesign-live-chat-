import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const cssDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/css');
const jsPath = join(__dirname, '../paxdesign-booking/assets/customer-auth/js/pax-auth.js');

const css = [
  'pdx-auth-page.css',
  'pdx-account-app.css',
  'pdx-portal-apple.css',
].map((f) => readFileSync(join(cssDir, f), 'utf8')).join('\n');

function buildHtml() {
  return `<!DOCTYPE html>
<html class="pdx-auth-isolated">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>${css}</style>
</head>
<body class="pdx-auth-isolated pdx-auth-page-body pdx-account-dashboard-body">
<div id="pdx-auth-isolated-shell">
  <div id="pdx-account-header" class="pdx-account-header" role="banner">
    <button type="button" id="pdx-test-menu" class="pdx-account-mobile-menu pdx-account-mobile-menu--in-header">Menu</button>
    <a id="pdx-account-header-home" class="pdx-account-header-home">Logo</a>
  </div>
  <div id="pdx-auth-page" class="pdx-auth-page">
    <div id="pdx-account-app" class="pdx-account-app">
      <aside class="pdx-account-sidebar" id="pdx-account-sidebar"></aside>
      <div role="main" class="pdx-account-main" id="pdx-account-main">
        <div class="pdx-account-page-head"><h2 class="pdx-account-page-title">Overview</h2></div>
        <div class="pdx-account-portal-host"><div class="pdx-portal-section"><h3>Dashboard</h3><p>Content visible</p></div></div>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var groups = [
    { label: 'Account', items: [
      { id: 'overview', label: 'Overview' }, { id: 'personal', label: 'Personal Information' },
      { id: 'security', label: 'Security' }, { id: 'settings', label: 'Settings' }
    ]},
    { label: 'Your Work', items: [
      { id: 'projects', label: 'Projects' }, { id: 'orders', label: 'Requests' },
      { id: 'records', label: 'Records' }, { id: 'files', label: 'Files & Invoices' }
    ]},
    { label: 'Updates', items: [
      { id: 'news', label: 'News' }, { id: 'notifications', label: 'Alerts' }
    ]},
    { label: 'Support', items: [
      { id: 'support', label: 'Messages' }, { id: 'services', label: 'Services' }
    ]}
  ];
  var sidebar = document.getElementById('pdx-account-sidebar');
  var html = '<div class="pdx-account-sidebar-mobile-head"><span class="pdx-account-sidebar-mobile-title">Account navigation</span><button type="button" class="pdx-account-sidebar-close">X</button></div><div class="pdx-account-sidebar-user"><div class="pdx-account-sidebar-name">Test User</div></div><div class="pdx-account-sidebar-nav">';
  groups.forEach(function(g){
    html += '<div class="pdx-account-nav-group"><div class="pdx-account-nav-label">' + g.label + '</div>';
    g.items.forEach(function(item){ html += '<button type="button" class="pdx-account-nav-btn" data-account-section="' + item.id + '"><span class="pdx-account-nav-text">' + item.label + '</span></button>'; });
    html += '</div>';
  });
  html += '</div><div class="pdx-account-sidebar-footer"><button type="button" class="pdx-account-signout">Sign Out</button></div>';
  sidebar.innerHTML = html;

  var backdrop = document.createElement('div');
  backdrop.className = 'pdx-account-mobile-backdrop pdx-account-mobile-backdrop--portal';
  var shell = document.getElementById('pdx-auth-isolated-shell');
  function mountOverlay(){
    if (window.matchMedia('(max-width: 900px)').matches) {
      shell.appendChild(backdrop);
      shell.appendChild(sidebar);
      sidebar.classList.add('pdx-account-sidebar--mobile-overlay');
    }
  }
  function openNav(){
    mountOverlay();
    document.body.classList.add('pdx-account-mobile-nav-open');
    sidebar.setAttribute('aria-hidden', 'false');
  }
  document.getElementById('pdx-test-menu').addEventListener('click', openNav);
  mountOverlay();
})();
</script>
</body>
</html>`;
}

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

async function measure(label, width, height) {
  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  await page.setContent(buildHtml(), { waitUntil: 'domcontentloaded' });
  await page.click('#pdx-test-menu').catch(function(){});
  await new Promise((r) => setTimeout(r, 400));
  const result = await page.evaluate(() => {
    const cs = (el) => el ? getComputedStyle(el) : null;
    const rect = (el) => el ? el.getBoundingClientRect() : null;
    const app = document.getElementById('pdx-account-app');
    const sidebar = document.getElementById('pdx-account-sidebar');
    const main = document.getElementById('pdx-account-main');
    const shell = document.getElementById('pdx-auth-isolated-shell');
    const navBtns = sidebar ? sidebar.querySelectorAll('[data-account-section]').length : 0;
    const sidebarRect = rect(sidebar);
    const mainRect = rect(main);
    const sidebarParent = sidebar && sidebar.parentElement ? sidebar.parentElement.id : null;
    const sidebarBg = cs(sidebar)?.backgroundColor || '';
    const sidebarBgOpaque = sidebarBg === 'rgb(255, 255, 255)' || sidebarBg === '#ffffff';
    const navLabelColor = cs(sidebar?.querySelector('.pdx-account-nav-label'))?.color || '';
    return {
      appDisplay: cs(app)?.display,
      sidebarDisplay: cs(sidebar)?.display,
      sidebarPosition: cs(sidebar)?.position,
      sidebarTransform: cs(sidebar)?.transform,
      sidebarParent,
      sidebarVisible: !!(sidebarRect && sidebarRect.width > 100 && sidebarRect.height > 100),
      sidebarLeft: sidebarRect ? Math.round(sidebarRect.left) : null,
      mainVisible: !!(mainRect && mainRect.width > 100 && mainRect.height > 100),
      mainLeft: mainRect ? Math.round(mainRect.left) : null,
      navItemCount: navBtns,
      sidebarBg,
      sidebarBgOpaque,
      navLabelColor,
      bodyOpen: document.body.classList.contains('pdx-account-mobile-nav-open'),
      shellContainsSidebar: !!(shell && sidebar && shell.contains(sidebar)),
    };
  });
  console.log('\n=== ' + label + ' (' + width + 'x' + height + ') ===');
  console.log(JSON.stringify(result, null, 2));
  await page.close();
  return result;
}

const desktop = await measure('Desktop', 1280, 800);
const mobile = await measure('Mobile', 390, 844);

let failed = 0;
if (desktop.appDisplay !== 'grid') { console.error('FAIL desktop: app should be grid, got ' + desktop.appDisplay); failed++; }
if (desktop.sidebarPosition !== 'static') { console.error('FAIL desktop: sidebar should be static, got ' + desktop.sidebarPosition); failed++; }
if (!desktop.mainVisible || desktop.mainLeft < 200) { console.error('FAIL desktop: main should be visible on the right'); failed++; }
if (!mobile.shellContainsSidebar) { console.error('FAIL mobile: sidebar should mount inside isolated shell'); failed++; }
if (mobile.navItemCount < 12) { console.error('FAIL mobile: expected 12 nav items, got ' + mobile.navItemCount); failed++; }
if (!mobile.sidebarVisible) { console.error('FAIL mobile: sidebar overlay should be visible when menu opens'); failed++; }
if (!mobile.sidebarBgOpaque) { console.error('FAIL mobile: sidebar overlay background must be solid opaque white'); failed++; }
if (!mobile.mainVisible) { console.error('FAIL mobile: main content should remain visible'); failed++; }

await browser.close();
if (failed) process.exit(1);
console.log('\nAll layout checks passed.');
