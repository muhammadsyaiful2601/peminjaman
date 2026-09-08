/**
 * Siapkan desktop/resources/ untuk electron-builder:
 *   - resources/backend        : salinan backend Laravel (tanpa .env, junk, db)
 *   - resources/frontend-dist  : hasil build frontend React
 *   - resources/php            : runtime PHP portable (dari prepare-php.ps1)
 *
 * Jalankan: npm run prepare-build
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const desktopDir = path.resolve(here, '..');
const repoRoot = path.resolve(desktopDir, '..');
const resourcesDir = path.join(desktopDir, 'resources');

const backendSrc = path.join(repoRoot, 'backend');
const frontendDistSrc = path.join(repoRoot, 'frontend', 'dist');
const phpDir = path.join(resourcesDir, 'php');

function rm(dir) {
  fs.rmSync(dir, { recursive: true, force: true });
}

function human(bytes) {
  const units = ['B', 'KB', 'MB', 'GB'];
  let v = bytes;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i += 1;
  }
  return `${v.toFixed(1)} ${units[i]}`;
}

function dirSize(dir) {
  let total = 0;
  if (!fs.existsSync(dir)) return 0;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    total += entry.isDirectory() ? dirSize(p) : fs.statSync(p).size;
  }
  return total;
}

// 1) Backend template
const backendDest = path.join(resourcesDir, 'backend');
console.log('[build] Menyalin backend ->', backendDest);
rm(backendDest);
fs.mkdirSync(backendDest, { recursive: true });
fs.cpSync(backendSrc, backendDest, {
  recursive: true,
  filter: (src) => {
    const rel = path.relative(backendSrc, src).replace(/\\/g, '/');
    if (rel === '') return true;
    if (rel === '.env' || rel === 'public/storage' || rel === '.git' || rel === 'database/database.sqlite') return false;
    if (rel.startsWith('node_modules') || rel.startsWith('tests')) return false;
    for (const keep of [
      'storage/framework/cache/data',
      'storage/framework/views',
      'storage/framework/sessions',
      'storage/logs',
      'storage/app/private',
      'storage/app/public',
    ]) {
      if (rel === keep) return true;
      if (rel.startsWith(keep + '/')) return false;
    }
    return true;
  },
});

// 2) Frontend dist
if (!fs.existsSync(path.join(frontendDistSrc, 'index.html'))) {
  console.error('[build] frontend/dist/index.html tidak ada. Jalankan "npm run build" di folder frontend dulu.');
  process.exit(1);
}
const distDest = path.join(resourcesDir, 'frontend-dist');
console.log('[build] Menyalin frontend/dist ->', distDest);
rm(distDest);
fs.cpSync(frontendDistSrc, distDest, { recursive: true });

// 3) PHP runtime
if (!fs.existsSync(path.join(phpDir, 'php.exe'))) {
  console.warn('[build] !! resources/php/php.exe tidak ditemukan.');
  console.warn('[build]    Jalankan: powershell -ExecutionPolicy Bypass -File scripts\\prepare-php.ps1');
  console.warn('[build]    (build tetap bisa berjalan, tapi installer tanpa PHP tidak akan jalan)');
} else {
  console.log('[build] PHP runtime ditemukan di resources/php');
}

console.log('[build] Ukuran backend template :', human(dirSize(backendDest)));
console.log('[build] Ukuran frontend dist    :', human(dirSize(distDest)));
console.log('[build] Selesai. Lanjutkan dengan: npx electron-builder');
