import puppeteer from 'puppeteer';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const js = readFileSync(join(__dirname, '../paxdesign-booking/assets/customer-auth/js/pax-auth.js'), 'utf8');

function extractHelper(name) {
  const start = js.indexOf('function ' + name);
  if (start < 0) throw new Error('Missing ' + name);
  const slice = js.slice(start);
  const end = slice.indexOf('\n  function ');
  return slice.slice(0, end > 0 ? end : undefined);
}

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setContent('<!DOCTYPE html><html><body></body></html>');
await page.addScriptTag({ content: `
  var C = { version: '3.174.75', avatarPresets: [
    { id: 'pax-none', type: 'none', url: '' },
    { id: 'pax-01', type: 'portrait', url: 'https://paxdesign.at/wp-content/plugins/paxdesign-booking/assets/customer-auth/images/avatars/pax-01.gif?v=3.174.75' },
    { id: 'pax-02', type: 'portrait', url: 'https://paxdesign.at/wp-content/plugins/paxdesign-booking/assets/customer-auth/images/avatars/pax-02.gif?v=3.174.75' },
  ], defaultAvatarUrl: 'https://paxdesign.at/wp-content/plugins/paxdesign-booking/assets/customer-auth/images/avatars/pax-01.gif?v=3.174.75' };
  ${extractHelper('normalizeAvatarAssetUrl')}
  ${extractHelper('accountAvatarPresets')}
  ${extractHelper('accountAvatarPresetUrl')}
` });

const result = await page.evaluate(() => ({
  svgToGif: normalizeAvatarAssetUrl('https://paxdesign.at/wp-content/plugins/paxdesign-booking/assets/customer-auth/images/avatars/pax-03.svg'),
  gifVersion: normalizeAvatarAssetUrl('https://paxdesign.at/wp-content/plugins/paxdesign-booking/assets/customer-auth/images/avatars/pax-04.gif'),
  presetFromId: accountAvatarPresetUrl('pax-02'),
  presetCatalog: accountAvatarPresets().filter((p) => p.id === 'pax-01')[0].url,
}));

await browser.close();

const ok =
  result.svgToGif.includes('pax-03.gif') &&
  !result.svgToGif.includes('.svg') &&
  result.gifVersion.includes('v=3.174.75') &&
  result.presetFromId.includes('pax-02.gif') &&
  result.presetCatalog.includes('pax-01.gif');

console.log(result, ok ? 'PASS' : 'FAIL');
if (!ok) process.exit(1);
console.log('Avatar URL normalization tests passed.');
