/**
 * Generate PAXDesign customer avatar presets (50 tech GIF avatars).
 * Run: node scripts/generate-pax-avatars.mjs
 */
import { spawnSync } from 'child_process';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const py = join(__dirname, 'generate-pax-gif-avatars.py');
const result = spawnSync('python3', [py], { stdio: 'inherit' });
process.exit(result.status ?? 1);
