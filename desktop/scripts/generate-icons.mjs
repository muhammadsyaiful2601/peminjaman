/**
 * Generate ikon aplikasi dari logo kampus (SVG) di frontend/src/assets.
 * Hasil: build/icon.png (512), build/icon.ico (multi-size Windows).
 *
 * Jalankan: npm run icons
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import pngToIco from 'png-to-ico';

const here = path.dirname(fileURLToPath(import.meta.url));
const desktopDir = path.resolve(here, '..');
const repoRoot = path.resolve(desktopDir, '..');
const buildDir = path.join(desktopDir, 'build');

const svgPath = path.join(
  repoRoot,
  'frontend',
  'src',
  'assets',
  'Logo_Politeknik_Negeri_Padang_(2014).svg',
);

if (!fs.existsSync(svgPath)) {
  console.error('[icons] Logo SVG tidak ditemukan:', svgPath);
  process.exit(1);
}

fs.mkdirSync(buildDir, { recursive: true });

// ICO Windows: ukuran maksimum yang valid adalah 256.
const icoSizes = [256, 128, 64, 48, 32, 16];
const pngBuffers = [];

for (const size of icoSizes) {
  const buf = await sharp(svgPath, { density: 512 })
    .resize(size, size, {
      fit: 'contain',
      background: { r: 255, g: 255, b: 255, alpha: 0 },
    })
    .png()
    .toBuffer();
  pngBuffers.push(buf);
}

try {
  const ico = await pngToIco(pngBuffers);
  fs.writeFileSync(path.join(buildDir, 'icon.ico'), ico);
  console.log('[icons] build/icon.ico dibuat dari logo PNP.');
} catch (e) {
  console.warn('[icons] Gagal membuat .ico:', e.message);
  console.warn('[icons] electron-builder akan mencoba konversi dari icon.png.');
}

// icon.png 512 terpisah (untuk BrowserWindow.icon & fallback builder).
const png512 = await sharp(svgPath, { density: 512 })
  .resize(512, 512, {
    fit: 'contain',
    background: { r: 255, g: 255, b: 255, alpha: 0 },
  })
  .png()
  .toBuffer();
fs.writeFileSync(path.join(buildDir, 'icon.png'), png512);
console.log('[icons] build/icon.png (512) dibuat.');
