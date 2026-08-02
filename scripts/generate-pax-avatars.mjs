import { writeFileSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const outDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/images/avatars');
mkdirSync(outDir, { recursive: true });

/** @typedef {{ id: string, label: string, skin: string, hair: string, bg1: string, bg2: string, style: string, expr: string, glasses?: boolean, beard?: boolean, accessory?: string, hairColor?: string }} FacePreset */

/** @type {FacePreset[]} */
const presets = [
  { id: 'pax-01', label: 'Professional — neutral', skin: '#F4C9A8', hair: '#3D2314', bg1: '#E8F0FE', bg2: '#C5D9F7', style: 'short-side', expr: 'neutral', hairColor: '#3D2314' },
  { id: 'pax-02', label: 'Friendly — warm smile', skin: '#E8B796', hair: '#1A1A1A', bg1: '#FFF4E6', bg2: '#FFD8A8', style: 'medium-wavy', expr: 'smile', hairColor: '#1A1A1A' },
  { id: 'pax-03', label: 'Serious — focused', skin: '#D4A574', hair: '#2C1810', bg1: '#F1F3F5', bg2: '#DEE2E6', style: 'buzz', expr: 'serious', hairColor: '#2C1810' },
  { id: 'pax-04', label: 'Playful — cheerful grin', skin: '#FFDBAC', hair: '#8B4513', bg1: '#FFF0F6', bg2: '#FFC9E5', style: 'curly-top', expr: 'grin', hairColor: '#A0522D' },
  { id: 'pax-05', label: 'Professional — glasses', skin: '#FDDCB5', hair: '#4A3728', bg1: '#E3F2FD', bg2: '#BBDEFB', style: 'side-part', expr: 'neutral', glasses: true, hairColor: '#4A3728' },
  { id: 'pax-06', label: 'Friendly — soft waves', skin: '#C68642', hair: '#1B0F0A', bg1: '#F3E5F5', bg2: '#E1BEE7', style: 'long-waves', expr: 'smile', hairColor: '#1B0F0A' },
  { id: 'pax-07', label: 'Serious — beard', skin: '#E0AC69', hair: '#2D1B0E', bg1: '#ECEFF1', bg2: '#CFD8DC', style: 'short-top', expr: 'serious', beard: true, hairColor: '#2D1B0E' },
  { id: 'pax-08', label: 'Creative — bold look', skin: '#F5CBA7', hair: '#6B2D5C', bg1: '#FCE4EC', bg2: '#F8BBD0', style: 'bob', expr: 'grin', hairColor: '#6B2D5C' },
  { id: 'pax-09', label: 'Professional — ponytail', skin: '#D9A066', hair: '#0D0D0D', bg1: '#E0F7FA', bg2: '#B2EBF2', style: 'ponytail', expr: 'neutral', hairColor: '#0D0D0D' },
  { id: 'pax-10', label: 'Friendly — round glasses', skin: '#FFCC99', hair: '#5C4033', bg1: '#FFF8E1', bg2: '#FFECB3', style: 'short-crop', expr: 'smile', glasses: true, hairColor: '#5C4033' },
  { id: 'pax-11', label: 'Calm — relaxed', skin: '#8D5524', hair: '#1A1008', bg1: '#EFEBE9', bg2: '#D7CCC8', style: 'afro', expr: 'neutral', hairColor: '#1A1008' },
  { id: 'pax-12', label: 'Energetic — bright smile', skin: '#F1C27D', hair: '#B8860B', bg1: '#FFF3E0', bg2: '#FFE0B2', style: 'spiky', expr: 'grin', hairColor: '#DAA520' },
  { id: 'pax-13', label: 'Executive — refined', skin: '#E8BEAC', hair: '#Silver', bg1: '#E8EAF6', bg2: '#C5CAE9', style: 'silver-short', expr: 'neutral', hairColor: '#9E9E9E' },
  { id: 'pax-14', label: 'Warm — welcoming', skin: '#C08A5A', hair: '#2E1A12', bg1: '#FBE9E7', bg2: '#FFCCBC', style: 'long-straight', expr: 'smile', hairColor: '#2E1A12' },
  { id: 'pax-15', label: 'Thoughtful — studious', skin: '#FFE0BD', hair: '#3E2723', bg1: '#F1F8E9', bg2: '#DCEDC8', style: 'messy', expr: 'serious', glasses: true, hairColor: '#3E2723' },
  { id: 'pax-16', label: 'Confident — goatee', skin: '#BF8F60', hair: '#1C1209', bg1: '#FAFAFA', bg2: '#EEEEEE', style: 'fade', expr: 'neutral', beard: true, hairColor: '#1C1209' },
  { id: 'pax-17', label: 'Approachable — dimples', skin: '#FFDBAC', hair: '#7B3F00', bg1: '#E1F5FE', bg2: '#B3E5FC', style: 'medium-layered', expr: 'grin', hairColor: '#7B3F00' },
  { id: 'pax-18', label: 'Minimal — clean cut', skin: '#F5DEB3', hair: '#212121', bg1: '#F5F5F5', bg2: '#E0E0E0', style: 'buzz', expr: 'neutral', hairColor: '#212121' },
  { id: 'pax-19', label: 'Artistic — colorful hair', skin: '#EAC086', hair: '#0071e3', bg1: '#EDE7F6', bg2: '#D1C4E9', style: 'pixie-blue', expr: 'smile', hairColor: '#0071e3' },
  { id: 'pax-20', label: 'Reliable — full beard', skin: '#D4A76A', hair: '#3D2914', bg1: '#ECEFF1', bg2: '#B0BEC5', style: 'short-side', expr: 'serious', beard: true, hairColor: '#3D2914' },
  { id: 'pax-21', label: 'Bright — optimistic', skin: '#FFCC99', hair: '#FF6B6B', bg1: '#FFF9C4', bg2: '#FFF176', style: 'bob-red', expr: 'grin', hairColor: '#E53935' },
  { id: 'pax-22', label: 'Composed — analyst', skin: '#C8956C', hair: '#1A1A2E', bg1: '#E8F5E9', bg2: '#C8E6C9', style: 'slick-back', expr: 'neutral', glasses: true, hairColor: '#1A1A2E' },
  { id: 'pax-23', label: 'Supportive — kind eyes', skin: '#F0C9A0', hair: '#4E342E', bg1: '#FCE4EC', bg2: '#F8BBD9', style: 'shoulder-length', expr: 'smile', hairColor: '#4E342E' },
  { id: 'pax-24', label: 'Dynamic — modern fade', skin: '#8D5524', hair: '#0A0A0A', bg1: '#E3F2FD', bg2: '#90CAF9', style: 'fade', expr: 'grin', hairColor: '#0A0A0A' },
  { id: 'pax-25', label: 'Precise — rectangular glasses', skin: '#FFE4C4', hair: '#5D4037', bg1: '#FFFDE7', bg2: '#FFF9C4', style: 'side-part', expr: 'serious', glasses: true, hairColor: '#5D4037' },
  { id: 'pax-26', label: 'Gentle — soft features', skin: '#FFDAB9', hair: '#BCAAA4', bg1: '#F3E5F5', bg2: '#E1BEE7', style: 'bun', expr: 'smile', hairColor: '#A1887F' },
  { id: 'pax-27', label: 'Bold — statement beard', skin: '#CD853F', hair: '#2F1B0C', bg1: '#EFEBE9', bg2: '#BCAAA4', style: 'short-top', expr: 'neutral', beard: true, hairColor: '#2F1B0C' },
  { id: 'pax-28', label: 'Upbeat — laughing', skin: '#F4A460', hair: '#654321', bg1: '#FFF3E0', bg2: '#FFCC80', style: 'curly-top', expr: 'laugh', hairColor: '#654321' },
  { id: 'pax-29', label: 'Focused — professional bun', skin: '#DEB887', hair: '#212121', bg1: '#ECEFF1', bg2: '#CFD8DC', style: 'top-bun', expr: 'serious', hairColor: '#212121' },
  { id: 'pax-30', label: 'Open — friendly gaze', skin: '#E8B4B8', hair: '#880E4F', bg1: '#FCE4EC', bg2: '#F48FB1', style: 'long-waves', expr: 'smile', hairColor: '#AD1457' },
  { id: 'pax-31', label: 'Steady — classic look', skin: '#D2B48C', hair: '#4A3728', bg1: '#E8EAF6', bg2: '#9FA8DA', style: 'short-side', expr: 'neutral', hairColor: '#4A3728' },
  { id: 'pax-32', label: 'Inventive — teal streak', skin: '#FFDBAC', hair: '#00695C', bg1: '#E0F2F1', bg2: '#80CBC4', style: 'asymmetric', expr: 'grin', hairColor: '#00897B' },
  { id: 'pax-33', label: 'Trusted — aviator glasses', skin: '#C68642', hair: '#1B1008', bg1: '#FFF8E1', bg2: '#FFECB3', style: 'short-crop', expr: 'neutral', glasses: true, hairColor: '#1B1008' },
  { id: 'pax-34', label: 'Graceful — elegant', skin: '#F5CBA7', hair: '#37474F', bg1: '#F3E5F5', bg2: '#CE93D8', style: 'long-straight', expr: 'smile', hairColor: '#37474F' },
  { id: 'pax-35', label: 'Determined — sharp', skin: '#E0AC69', hair: '#0D0D0D', bg1: '#FAFAFA', bg2: '#E0E0E0', style: 'undercut', expr: 'serious', hairColor: '#0D0D0D' },
  { id: 'pax-36', label: 'Sunny — radiant smile', skin: '#FFCC99', hair: '#F57F17', bg1: '#FFFDE7', bg2: '#FFF59D', style: 'medium-wavy', expr: 'grin', hairColor: '#F9A825' },
  { id: 'pax-37', label: 'Wise — silver hair', skin: '#E8BEAC', hair: '#BDBDBD', bg1: '#ECEFF1', bg2: '#B0BEC5', style: 'silver-short', expr: 'neutral', glasses: true, hairColor: '#BDBDBD' },
  { id: 'pax-38', label: 'Vibrant — curly afro', skin: '#8D5524', hair: '#1A1008', bg1: '#E8F5E9', bg2: '#A5D6A7', style: 'afro', expr: 'smile', hairColor: '#1A1008' },
  { id: 'pax-39', label: 'Cool — subtle smirk', skin: '#D9A066', hair: '#263238', bg1: '#E1F5FE', bg2: '#81D4FA', style: 'messy', expr: 'smirk', hairColor: '#263238' },
  { id: 'pax-40', label: 'Polished — corporate', skin: '#FDDCB5', hair: '#3E2723', bg1: '#E8EAF6', bg2: '#C5CAE9', style: 'slick-back', expr: 'neutral', hairColor: '#3E2723' },
  { id: 'pax-41', label: 'Caring — warm eyes', skin: '#F0C9A0', hair: '#5D4037', bg1: '#FBE9E7', bg2: '#FFAB91', style: 'shoulder-length', expr: 'smile', hairColor: '#5D4037' },
  { id: 'pax-42', label: 'Adventurous — stubble', skin: '#BF8F60', hair: '#2C1810', bg1: '#FFF3E0', bg2: '#FFCC80', style: 'short-top', expr: 'grin', beard: true, hairColor: '#2C1810' },
  { id: 'pax-43', label: 'Distinctive — hoop earrings', skin: '#C8956C', hair: '#1A1A1A', bg1: '#FCE4EC', bg2: '#F48FB1', style: 'pixie', expr: 'smile', accessory: 'earrings', hairColor: '#1A1A1A' },
  { id: 'pax-44', label: 'Measured — thoughtful', skin: '#FFE0BD', hair: '#4E342E', bg1: '#F1F8E9', bg2: '#C5E1A5', style: 'side-part', expr: 'serious', glasses: true, hairColor: '#4E342E' },
  { id: 'pax-45', label: 'Lively — playful wink', skin: '#FFDBAC', hair: '#6A1B9A', bg1: '#EDE7F6', bg2: '#B39DDB', style: 'bob', expr: 'wink', hairColor: '#7B1FA2' },
  { id: 'pax-46', label: 'Grounded — steady', skin: '#CD853F', hair: '#212121', bg1: '#EFEBE9', bg2: '#D7CCC8', style: 'buzz', expr: 'neutral', hairColor: '#212121' },
  { id: 'pax-47', label: 'Refined — cat-eye glasses', skin: '#F5DEB3', hair: '#880E4F', bg1: '#F3E5F5', bg2: '#E1BEE7', style: 'updo', expr: 'neutral', glasses: true, hairColor: '#AD1457' },
  { id: 'pax-48', label: 'Easygoing — relaxed grin', skin: '#EAC086', hair: '#5C4033', bg1: '#E0F7FA', bg2: '#80DEEA', style: 'medium-layered', expr: 'grin', hairColor: '#5C4033' },
  { id: 'pax-49', label: 'Leadership — distinguished', skin: '#D4A574', hair: '#616161', bg1: '#ECEFF1', bg2: '#90A4AE', style: 'silver-short', expr: 'serious', beard: true, hairColor: '#757575' },
  { id: 'pax-50', label: 'PAXDesign — signature', skin: '#F4C9A8', hair: '#0071e3', bg1: '#0071e3', bg2: '#005bb5', style: 'short-side', expr: 'smile', hairColor: '#0071e3' },
];

function hairPath(style, color) {
  const paths = {
    'short-side': `<path d="M18 28c0-10 6-16 14-16s14 6 14 16v6c-3-8-10-12-14-12s-11 4-14 12V28z" fill="${color}"/>`,
    'medium-wavy': `<path d="M16 30c0-12 7-18 16-18s16 6 16 18v8c-4-10-12-14-16-14s-12 4-16 14v-8z" fill="${color}"/><path d="M14 26c3-6 10-10 18-10s15 4 18 10" fill="none" stroke="${color}" stroke-width="3"/>`,
    buzz: `<ellipse cx="32" cy="24" rx="15" ry="10" fill="${color}"/>`,
    'curly-top': `<path d="M18 30c0-10 5-16 14-16 4 0 8 2 10 5 2-6 8-10 14-10 5 0 10 4 12 10-2 8-8 14-16 14H22c-8 0-12-6-14-14z" fill="${color}"/>`,
    'side-part': `<path d="M17 28c0-10 7-16 15-16 5 0 9 3 11 7 1-8 7-12 14-12 6 0 11 5 12 12v5c-4-7-11-11-18-11s-16 4-20 11v4z" fill="${color}"/>`,
    'long-waves': `<path d="M14 32c0-14 8-20 18-20s18 6 18 20v14c-6-12-14-16-18-16s-12 4-18 16V32z" fill="${color}"/>`,
    'short-top': `<path d="M19 27c0-9 5-14 13-14s13 5 13 14v4c-2-6-8-9-13-9s-11 3-13 9v-4z" fill="${color}"/>`,
    bob: `<path d="M16 30c0-11 7-17 16-17s16 6 16 17v10c-3-8-10-12-16-12s-13 4-16 12V30z" fill="${color}"/>`,
    ponytail: `<path d="M18 28c0-10 6-15 14-15s14 5 14 15v4H18v-4z" fill="${color}"/><ellipse cx="46" cy="26" rx="5" ry="8" fill="${color}"/>`,
    'short-crop': `<path d="M20 27c0-9 6-14 12-14s12 5 12 14v5c-2-5-7-8-12-8s-10 3-12 8v-5z" fill="${color}"/>`,
    afro: `<circle cx="32" cy="24" r="16" fill="${color}"/>`,
    spiky: `<path d="M20 28l4-14 4 10 4-12 4 12 4-10 4 14H20z" fill="${color}"/>`,
    'silver-short': `<path d="M19 28c0-9 6-14 13-14s13 5 13 14v5c-2-6-8-9-13-9s-11 3-13 9v-5z" fill="${color}"/>`,
    'long-straight': `<path d="M15 32c0-13 8-19 17-19s17 6 17 19v16H15V32z" fill="${color}"/>`,
    messy: `<path d="M17 29c2-10 9-15 15-15 5 0 10 3 13 8 2-5 7-9 13-9 4 0 8 2 10 6v6c-5-8-13-12-20-12s-17 4-22 12v4z" fill="${color}"/>`,
    fade: `<path d="M18 27c0-8 7-13 14-13s14 5 14 13v3H18v-3z" fill="${color}"/><path d="M16 30h32" stroke="${color}" stroke-width="4"/>`,
    'medium-layered': `<path d="M16 30c0-11 8-17 16-17s16 6 16 17v8c-3-7-9-11-16-11s-13 4-16 11v-8z" fill="${color}"/>`,
    'pixie-blue': `<path d="M20 28c0-9 7-14 12-14s12 5 12 14v6l-6-4-6 2-6-2-6 4v-6z" fill="${color}"/>`,
    'bob-red': `<path d="M17 30c0-11 7-17 15-17s15 6 15 17v11c-3-7-9-11-15-11s-12 4-15 11V30z" fill="${color}"/>`,
    'slick-back': `<path d="M17 28c0-9 8-14 15-14s15 5 15 14v6H17v-6z" fill="${color}"/><path d="M17 24c5-4 15-4 30 0" fill="none" stroke="${color}" stroke-width="3"/>`,
    'shoulder-length': `<path d="M14 31c0-12 8-18 18-18s18 6 18 18v12c-5-9-12-13-18-13s-13 4-18 13V31z" fill="${color}"/>`,
    undercut: `<path d="M20 26c0-7 6-11 12-11s12 4 12 11v2H20v-2z" fill="${color}"/><path d="M18 28h28" stroke="${color}" stroke-width="5"/>`,
    asymmetric: `<path d="M18 29c0-10 8-16 14-16 4 0 8 2 10 5 2-7 9-11 16-11 5 0 9 3 10 8v8H18V29z" fill="${color}"/>`,
    'top-bun': `<path d="M20 28c0-9 6-14 12-14s12 5 12 14v4H20v-4z" fill="${color}"/><circle cx="32" cy="16" r="6" fill="${color}"/>`,
    pixie: `<path d="M20 28c0-9 7-14 12-14s12 5 12 14v5c-2-5-6-8-12-8s-10 3-12 8v-5z" fill="${color}"/>`,
    updo: `<path d="M22 28c0-8 5-12 10-12s10 4 10 12v4H22v-4z" fill="${color}"/><ellipse cx="32" cy="17" rx="10" ry="6" fill="${color}"/>`,
  };
  return paths[style] || paths['short-side'];
}

function expressionPaths(expr, skin) {
  const eyeY = 33;
  const mouthY = 44;
  const shadow = '#2C1810';

  const eyes = {
    neutral: `<ellipse cx="26" cy="${eyeY}" rx="2.2" ry="2.8" fill="${shadow}"/><ellipse cx="38" cy="${eyeY}" rx="2.2" ry="2.8" fill="${shadow}"/>`,
    smile: `<path d="M24 33q2-2 4 0M36 33q2-2 4 0" fill="none" stroke="${shadow}" stroke-width="1.8" stroke-linecap="round"/><path d="M26 33.5a2 2 0 004 0M34 33.5a2 2 0 004 0" fill="${shadow}"/>`,
    serious: `<rect x="24" y="31.5" width="4" height="2.5" rx="1" fill="${shadow}"/><rect x="36" y="31.5" width="4" height="2.5" rx="1" fill="${shadow}"/>`,
    grin: `<path d="M24 32.5a3 3 0 006 0M34 32.5a3 3 0 006 0" fill="${shadow}"/><path d="M27 46q5 5 10 0" fill="none" stroke="#C0392B" stroke-width="2" stroke-linecap="round"/>`,
    laugh: `<path d="M23 32a3.5 3.5 0 017 0M33 32a3.5 3.5 0 017 0" fill="${shadow}"/><path d="M24 45q8 8 16 0" fill="none" stroke="#C0392B" stroke-width="2.2" stroke-linecap="round"/><path d="M26 44h2M36 44h2" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/>`,
    smirk: `<ellipse cx="26" cy="${eyeY}" rx="2" ry="2.5" fill="${shadow}"/><path d="M36 31.5a3 2 0 015 1" fill="none" stroke="${shadow}" stroke-width="2" stroke-linecap="round"/><path d="M28 45q6 3 10-1" fill="none" stroke="#C0392B" stroke-width="1.8" stroke-linecap="round"/>`,
    wink: `<ellipse cx="26" cy="${eyeY}" rx="2.2" ry="2.8" fill="${shadow}"/><path d="M36 33.5h5" stroke="${shadow}" stroke-width="2" stroke-linecap="round"/><path d="M27 45q5 4 10 0" fill="none" stroke="#C0392B" stroke-width="2" stroke-linecap="round"/>`,
  };

  const brows = {
    neutral: `<path d="M22 28q4-2 8 0M34 28q4-2 8 0" fill="none" stroke="${shadow}" stroke-width="1.5" stroke-linecap="round" opacity=".7"/>`,
    smile: `<path d="M22 27.5q4-1 8 1M34 27.5q4-1 8 1" fill="none" stroke="${shadow}" stroke-width="1.5" stroke-linecap="round" opacity=".65"/>`,
    serious: `<path d="M22 29l8-1M34 28l8 1" fill="none" stroke="${shadow}" stroke-width="1.6" stroke-linecap="round" opacity=".75"/>`,
    grin: `<path d="M21 27q5-2 9 0M34 27q5-2 9 0" fill="none" stroke="${shadow}" stroke-width="1.5" stroke-linecap="round" opacity=".6"/>`,
    laugh: `<path d="M21 26.5q5-1 9 1M34 26.5q5-1 9 1" fill="none" stroke="${shadow}" stroke-width="1.5" stroke-linecap="round" opacity=".55"/>`,
    smirk: `<path d="M22 28q4-2 8 0M35 27l7 2" fill="none" stroke="${shadow}" stroke-width="1.5" stroke-linecap="round" opacity=".7"/>`,
    wink: `<path d="M22 28q4-2 8 0M34 27.5q4-1 8 1" fill="none" stroke="${shadow}" stroke-width="1.5" stroke-linecap="round" opacity=".65"/>`,
  };

  const mouths = {
    neutral: `<path d="M27 44h10" stroke="#B5654A" stroke-width="1.8" stroke-linecap="round"/>`,
    smile: `<path d="M26 44q6 5 12 0" fill="none" stroke="#B5654A" stroke-width="1.8" stroke-linecap="round"/>`,
    serious: `<path d="M28 45h8" stroke="#B5654A" stroke-width="2" stroke-linecap="round"/>`,
    grin: `<path d="M25 43q7 7 14 0" fill="none" stroke="#B5654A" stroke-width="2" stroke-linecap="round"/>`,
    laugh: `<path d="M24 42q8 9 16 0" fill="#fff" stroke="#B5654A" stroke-width="1.5"/>`,
    smirk: `<path d="M28 44q5 2 9-1" fill="none" stroke="#B5654A" stroke-width="1.8" stroke-linecap="round"/>`,
    wink: `<path d="M26 44q6 4 12 0" fill="none" stroke="#B5654A" stroke-width="1.8" stroke-linecap="round"/>`,
  };

  const e = expr in eyes ? expr : 'neutral';
  return `${brows[e] || brows.neutral}${eyes[e] || eyes.neutral}<ellipse cx="32" cy="39" rx="2" ry="1.5" fill="#D4956A" opacity=".45"/>${mouths[e] || mouths.neutral}`;
}

function glassesSvg() {
  return `<g fill="none" stroke="#37474F" stroke-width="1.8" opacity=".92">
    <circle cx="26" cy="33" r="5.5"/>
    <circle cx="38" cy="33" r="5.5"/>
    <path d="M31.5 33h1"/>
    <path d="M20.5 32h2.5M41 32h2.5"/>
  </g>`;
}

function beardSvg(color) {
  return `<path d="M22 42c2 8 7 12 10 12s8-4 10-12c-3 5-7 7-10 7s-7-2-10-7z" fill="${color}" opacity=".85"/>`;
}

function accessorySvg(type) {
  if (type === 'earrings') {
    return `<circle cx="17" cy="36" r="2" fill="none" stroke="#FFD700" stroke-width="1.5"/><circle cx="47" cy="36" r="2" fill="none" stroke="#FFD700" stroke-width="1.5"/>`;
  }
  return '';
}

function buildFaceSvg(p) {
  const hairColor = p.hairColor || p.hair;
  const uid = p.id.replace(/[^a-z0-9]/gi, '');
  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="${p.label}">
  <defs>
    <linearGradient id="bg-${uid}" x1="8" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="${p.bg1}"/>
      <stop offset="100%" stop-color="${p.bg2}"/>
    </linearGradient>
    <clipPath id="clip-${uid}"><circle cx="32" cy="32" r="32"/></clipPath>
  </defs>
  <circle cx="32" cy="32" r="32" fill="url(#bg-${uid})"/>
  <g clip-path="url(#clip-${uid})">
    <ellipse cx="32" cy="58" rx="18" ry="10" fill="#4A5568" opacity=".18"/>
    ${hairPath(p.style, hairColor)}
    <ellipse cx="32" cy="38" rx="14" ry="16" fill="${p.skin}"/>
    <ellipse cx="32" cy="40" rx="11" ry="13" fill="${p.skin}"/>
    ${expressionPaths(p.expr, p.skin)}
    ${p.glasses ? glassesSvg() : ''}
    ${p.beard ? beardSvg(hairColor) : ''}
    ${p.accessory ? accessorySvg(p.accessory) : ''}
  </g>
</svg>
`;
}

for (const p of presets) {
  writeFileSync(join(outDir, `${p.id}.svg`), buildFaceSvg(p));
}

const phpEntries = presets.map((p) => `\tarray(\n\t\t'id'    => '${p.id}',\n\t\t'label' => '${p.label.replace(/'/g, "\\'")}',\n\t),`).join('\n');
const phpFile = `<?php
/**
 * PAXDesign customer avatar preset labels.
 *
 * @package PAXdesign_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
\texit;
}

return array(
${phpEntries}
);
`;
writeFileSync(join(__dirname, '../paxdesign-booking/includes/customer/data/avatar-preset-labels.php'), phpFile);

console.log(`Generated ${presets.length} PAXDesign face portrait SVGs.`);
