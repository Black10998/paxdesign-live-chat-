import { readFileSync, readdirSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const avatarDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/images/avatars');

const gifFiles = readdirSync(avatarDir).filter((f) => f.endsWith('.gif')).sort();

if (gifFiles.length !== 100) {
  console.error('Expected 100 GIF avatars, found', gifFiles.length);
  process.exit(1);
}

function countGifFrames(buf) {
  let frames = 0;
  for (let i = 0; i < buf.length - 1; i++) {
    if (buf[i] === 0x21 && buf[i + 1] === 0xf9) {
      frames++;
    }
  }
  return frames;
}

const staticFiles = [];
for (const file of gifFiles) {
  const buf = readFileSync(join(avatarDir, file));
  if (buf[0] !== 0x47 || buf[1] !== 0x49 || buf[2] !== 0x46) {
    console.error(`${file} is not a valid GIF`);
    process.exit(1);
  }
  const frames = countGifFrames(buf);
  if (frames < 2) {
    staticFiles.push({ file, frames });
  }
}

if (staticFiles.length) {
  console.error('Static or single-frame GIFs detected:', staticFiles);
  process.exit(1);
}

console.log(`Verified ${gifFiles.length} animated GIF avatars (multi-frame).`);
