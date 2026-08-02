import { readFileSync, readdirSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const vipDir = join(__dirname, '../paxdesign-booking/assets/customer-auth/images/avatars-vip');

const gifFiles = readdirSync(vipDir).filter((f) => f.endsWith('.gif')).sort();
if (gifFiles.length !== 10) {
  console.error('Expected 10 VIP GIF avatars, found', gifFiles.length);
  process.exit(1);
}

for (const file of gifFiles) {
  const buf = readFileSync(join(vipDir, file));
  if (buf[0] !== 0x47 || buf[1] !== 0x49) {
    console.error(`${file} is not a valid GIF`);
    process.exit(1);
  }
  let frames = 0;
  for (let i = 0; i < buf.length - 1; i++) {
    if (buf[i] === 0x21 && buf[i + 1] === 0xf9) frames++;
  }
  if (frames < 2) {
    console.error(`${file} is not animated`);
    process.exit(1);
  }
}

console.log(`Verified ${gifFiles.length} premium VIP animated GIF avatars.`);
