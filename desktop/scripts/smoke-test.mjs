/**
 * Smoke test headless untuk pipeline aplikasi desktop.
 * Mereplikasi alur boot main.js tanpa Electron:
 *   migrasi SQLite -> seed -> php -S + desktop-router.php -> health -> API -> SPA fallback
 *
 * Jalankan: node scripts/smoke-test.mjs   (butuh php & frontend/dist)
 */
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import http from 'node:http';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const desktopDir = path.resolve(here, '..');
const repoRoot = path.resolve(desktopDir, '..');
const backend = path.join(repoRoot, 'backend');
const dist = path.join(repoRoot, 'frontend', 'dist');
const workDir = path.join(desktopDir, '.smoke');
const dbFile = path.join(workDir, 'peminjaman.sqlite');
const smokeStorageDir = path.join(backend, 'storage', 'app', 'public');
const smokeStorageFile = path.join(smokeStorageDir, '.desktop-smoke.txt');
const PORT = 8643;
const BASE = `http://127.0.0.1:${PORT}`;

const results = [];
function record(name, ok, detail = '') {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ' — ' + detail : ''}`);
}

const env = {
  ...process.env,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: dbFile,
  APP_KEY: 'base64:' + crypto.randomBytes(32).toString('base64'),
  APP_ENV: 'testing',
  APP_URL: BASE,
  FRONTEND_URL: BASE,
  DESKTOP_FRONTEND_DIST: dist,
};

function run(args, timeoutMs = 120000) {
  return new Promise((resolve, reject) => {
    const child = spawn('php', args, { cwd: backend, env, windowsHide: true });
    let out = '';
    let err = '';
    const timer = setTimeout(() => reject(new Error(`timeout: ${args.join(' ')}`)), timeoutMs);
    child.stdout.on('data', (d) => (out += d));
    child.stderr.on('data', (d) => (err += d));
    child.on('error', (e) => {
      clearTimeout(timer);
      reject(e);
    });
    child.on('exit', (code) => {
      clearTimeout(timer);
      resolve({ code, out, err });
    });
  });
}

function request(method, url, body, headers = {}) {
  return new Promise((resolve, reject) => {
    const data = body ? Buffer.from(JSON.stringify(body)) : null;
    const req = http.request(
      BASE + url,
      {
        method,
        headers: {
          ...(data ? { 'Content-Type': 'application/json', 'Content-Length': data.length } : {}),
          ...headers,
        },
        timeout: 15000,
      },
      (res) => {
        const chunks = [];
        res.on('data', (c) => chunks.push(c));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: Buffer.concat(chunks) }));
      },
    );
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error('timeout ' + url)));
    if (data) req.write(data);
    req.end();
  });
}

async function waitHealth(timeoutMs = 45000) {
  const start = Date.now();
  for (;;) {
    try {
      const res = await request('GET', '/up');
      if (res.status < 500) return res;
    } catch {
      /* belum siap */
    }
    if (Date.now() - start > timeoutMs) throw new Error('health timeout');
    await new Promise((r) => setTimeout(r, 400));
  }
}

// ------------------------------------------------------------------ setup
fs.rmSync(workDir, { recursive: true, force: true });
fs.mkdirSync(workDir, { recursive: true });
fs.writeFileSync(dbFile, '');
fs.mkdirSync(smokeStorageDir, { recursive: true });
fs.writeFileSync(smokeStorageFile, 'desktop storage ok');

// 1. Migrasi
const mig = await run(['artisan', 'migrate', '--force']);
record('migrate --force (SQLite)', mig.code === 0, mig.code === 0 ? '' : (mig.err || mig.out).slice(-500));

// 2. Seed
const seed = await run(['artisan', 'db:seed', '--force']);
record('db:seed --force (akun admin)', seed.code === 0, seed.code === 0 ? '' : (seed.err || seed.out).slice(-500));

// 3. Server PHP + router
const server = spawn(
  'php',
  ['-S', `127.0.0.1:${PORT}`, '-t', 'public', 'desktop-router.php'],
  { cwd: backend, env, windowsHide: true },
);
let serverErr = '';
server.stderr.on('data', (d) => (serverErr += d));

try {
  // 4. Health
  const up = await waitHealth();
  record('GET /up (health check)', up.status === 200, `status=${up.status}`);

  // 5. SPA fallback
  const spa = await request('GET', '/');
  const isHtml = spa.status === 200 && spa.body.toString('utf8').includes('<div id="root">');
  record('GET / (SPA index.html)', isHtml, `status=${spa.status}`);

  const spaRoute = await request('GET', '/loans');
  record(
    'GET /loans (fallback SPA untuk React Router)',
    spaRoute.status === 200 && String(spaRoute.headers['content-type']).includes('text/html'),
    `status=${spaRoute.status}`,
  );

  const storageAsset = await request('GET', '/storage/.desktop-smoke.txt');
  record(
    'GET /storage/* (file upload Laravel)',
    storageAsset.status === 200 && storageAsset.body.toString() === 'desktop storage ok',
    `status=${storageAsset.status}`,
  );

  const verificationRoute = await request('GET', '/email/verify/1/invalid');
  record(
    'GET /email/verify/* (route Laravel, bukan fallback SPA)',
    verificationRoute.status === 403,
    `status=${verificationRoute.status}`,
  );

  // 6. Aset frontend dengan MIME benar
  const indexHtml = spa.body.toString('utf8');
  const assetMatch = indexHtml.match(/src="(\/assets\/[^"]+\.js)"/);
  if (assetMatch) {
    const asset = await request('GET', assetMatch[1]);
    record(
      `GET ${assetMatch[1]} (MIME js)`,
      asset.status === 200 && String(asset.headers['content-type']).includes('javascript'),
      String(asset.headers['content-type']),
    );
  } else {
    record('aset frontend ditemukan di index.html', false, 'tag <script> tidak ditemukan');
  }

  // 7. Login API (sqlite)
  let login = await request('POST', '/api/login', { username: 'admin', password: 'password' });
  if (login.status === 422 || login.status === 404) {
    login = await request('POST', '/api/login', { email: 'admin', password: 'password' });
  }
  const token = (() => {
    try {
      return JSON.parse(login.body.toString('utf8')).token;
    } catch {
      return null;
    }
  })();
  record('POST /api/login (admin/password, Sanctum)', login.status === 200 && Boolean(token), `status=${login.status}`);

  // 8. Route desktop mail-test (tanpa SMTP aktif -> ok:false, tapi route hidup)
  const mailTest = await request('POST', '/api/desktop/mail-test', { to: 'tes@polpad.ac.id' });
  let mailJson = {};
  try {
    mailJson = JSON.parse(mailTest.body.toString('utf8'));
  } catch {
    /* abaikan */
  }
  record(
    'POST /api/desktop/mail-test (route desktop)',
    mailTest.status === 200 && typeof mailJson.ok === 'boolean' && Boolean(mailJson.message),
    `status=${mailTest.status} ok=${mailJson.ok} (${String(mailJson.message).slice(0, 80)})`,
  );

  // 9. Items API dengan token (verifikasi auth + db runtime)
  if (token) {
    const items = await request('GET', '/api/items', null, { Authorization: `Bearer ${token}` });
    record('GET /api/items (Bearer token)', items.status === 200, `status=${items.status}`);

    const report = await request('GET', '/api/loans/report/download', null, {
      Authorization: `Bearer ${token}`,
    });
    record(
      'GET /api/loans/report/download (PDF laporan)',
      report.status === 200 && String(report.headers['content-type']).includes('application/pdf'),
      `status=${report.status} type=${report.headers['content-type'] || ''}`,
    );
  }
} catch (e) {
  record('alur request', false, String(e.message) + (serverErr ? ' | server: ' + serverErr.slice(-300) : ''));
} finally {
  server.kill();
  fs.rmSync(smokeStorageFile, { force: true });
  try {
    if (process.platform === 'win32') {
      spawn('taskkill', ['/PID', String(server.pid), '/T', '/F'], { windowsHide: true });
    }
  } catch {
    /* abaikan */
  }
}

const failed = results.filter((r) => !r.ok);
console.log('');
console.log(`Selesai: ${results.length - failed.length}/${results.length} lolos.`);
fs.writeFileSync(path.join(workDir, 'result.json'), JSON.stringify(results, null, 2));
process.exit(failed.length ? 1 : 0);


