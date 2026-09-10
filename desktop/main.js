'use strict';

/**
 * Peminjaman Barang PNP — Aplikasi Desktop (Electron)
 *
 * Alur boot:
 *   1. Pastikan salinan runtime backend (template -> %APPDATA%) siap.
 *   2. Tulis .env runtime (SQLite di AppData, URL localhost, mail dari wizard).
 *   3. Migrasi + seed saat database pertama kali dibuat.
 *   4. Jalankan server PHP built-in (SPA React + API Laravel, satu origin).
 *   5. Tunggu /up sehat -> tampilkan setup wizard email (first-run) atau jendela utama.
 */

const { app, BrowserWindow, ipcMain, shell, Menu, dialog } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const net = require('net');
const http = require('http');

// Pembaruan otomatis dari GitHub Releases (electron-updater). Kosong saat
// paket tidak bisa dilaya (mode dev) — fitur tetap aktif pada versi terinstal.
let autoUpdater = null;
try {
  autoUpdater = require('electron-updater').autoUpdater;
} catch {
  autoUpdater = null;
}

const APP_TITLE = 'Peminjaman Barang — Politeknik Negeri Padang';
const PREFERRED_PORT = 8642;
// Naikkan versi template agar instalasi lama menyalin ulang runtime backend
// (termasuk perbaikan email bukti peminjaman yang dilampirkan langsung).
const TEMPLATE_VERSION = '1.0.9';
const isDev = !app.isPackaged;

/* ------------------------------------------------------------------ paths */

const repoRoot = path.join(__dirname, '..');

let phpBin;
let backendTemplate;
let frontendDist;
let cloudflaredBin;

if (isDev) {
  phpBin = process.env.DESKTOP_PHP || 'php';
  backendTemplate = path.join(repoRoot, 'backend');
  frontendDist = path.join(repoRoot, 'frontend', 'dist');
  cloudflaredBin = process.env.DESKTOP_CLOUDFLARED || path.join(__dirname, 'resources', 'cloudflared', 'cloudflared.exe');
} else {
  phpBin = path.join(process.resourcesPath, 'php', 'php.exe');
  backendTemplate = path.join(process.resourcesPath, 'backend');
  frontendDist = path.join(process.resourcesPath, 'frontend-dist');
  cloudflaredBin = path.join(process.resourcesPath, 'cloudflared', 'cloudflared.exe');
}

const userDataDir = app.getPath('userData');
const runtimeBackend = isDev ? backendTemplate : path.join(userDataDir, 'app', 'backend');
const databaseFile = path.join(userDataDir, 'peminjaman.sqlite');
const configPath = path.join(userDataDir, 'desktop-config.json');

/* ------------------------------------------------------------------ state */

let config = { setupDone: false, preferredPort: null, mail: null, appKey: null, desktopKey: null };
let phpServer = null;
let queueWorker = null;
let tunnelProcess = null;
let tunnelUrl = null;
let tunnelRetryTimer = null;
let mainWindow = null;
let setupWindow = null;
let splashWindow = null;
let backendPort = null;
let bootSucceeded = false;
let quitting = false;
let restartingBackend = false;
let phpServerError = '';
let updateState = { state: 'idle', version: null, percent: 0, message: '' };
let updatePromptOpen = false;
let updateCheckInProgress = false;
let updateCheckTimer = null;

function loadConfig() {
  try {
    config = { ...config, ...JSON.parse(fs.readFileSync(configPath, 'utf8')) };
  } catch {
    /* file belum ada -> pakai default */
  }
  if (!config.desktopKey) {
    // Secret untuk endpoint khusus desktop (X-Desktop-Key). Penting saat
    // server lokal diekspos ke internet melalui tunnel.
    config.desktopKey = crypto.randomBytes(24).toString('hex');
    saveConfig();
  }
}

function saveConfig() {
  fs.mkdirSync(path.dirname(configPath), { recursive: true });
  fs.writeFileSync(configPath, JSON.stringify(config, null, 2));
}

/* ------------------------------------------------------------- .env utils */

function parseEnv(text) {
  const out = {};
  for (const raw of String(text || '').split(/\r?\n/)) {
    const line = raw.trim();
    if (!line || line.startsWith('#') || line.startsWith(';')) continue;
    const eq = line.indexOf('=');
    if (eq === -1) continue;
    const key = line.slice(0, eq).trim();
    let val = line.slice(eq + 1).trim();
    const isDoubleQuoted = val.startsWith('"') && val.endsWith('"');
    if (isDoubleQuoted || (val.startsWith("'") && val.endsWith("'"))) {
      val = val.slice(1, -1);
      if (isDoubleQuoted) {
        val = val.replace(/\\(\\|"|n|r|t)/g, (_match, escaped) => ({
          '\\': '\\',
          '"': '"',
          n: '\n',
          r: '\r',
          t: '\t',
        })[escaped]);
      }
    }
    out[key] = val;
  }
  return out;
}

function readEnvFile(dir) {
  try {
    return parseEnv(fs.readFileSync(path.join(dir, '.env'), 'utf8'));
  } catch {
    return {};
  }
}

function writeEnvFile(dir, values) {
  const lines = Object.entries(values).map(([key, val]) => {
    const s = String(val ?? '');
    if (!/[\s"'#\\\n\r]/.test(s)) return `${key}=${s}`;
    const escaped = s.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\r/g, '\\r').replace(/\n/g, '\\n');
    return `${key}="${escaped}"`;
  });
  fs.writeFileSync(path.join(dir, '.env'), lines.join('\n') + '\n', 'utf8');
}

/** Ubah objek mail wizard menjadi pasangan variabel lingkungan MAIL_*. */
function mailEnv(mail) {
  if (!mail) return {};
  return {
    MAIL_MAILER: mail.useSmtp ? 'smtp' : 'log',
    MAIL_HOST: String(mail.host || '').trim() || 'smtp-relay.brevo.com',
    MAIL_PORT: String(mail.port || '587').trim() || '587',
    MAIL_USERNAME: String(mail.username || '').trim(),
    MAIL_PASSWORD: String(mail.password || ''),
    MAIL_FROM_ADDRESS: String(mail.fromAddress || '').trim() || 'no-reply@pnp.local',
    MAIL_FROM_NAME: String(mail.fromName || '').trim() || 'Peminjaman Barang PNP',
  };
}

/** Susun isi .env runtime: nilai desktop + yang sudah ada + wizard mail (menang). */
function buildDesktopEnv(port) {
  const existing = isDev ? readEnvFile(runtimeBackend) : {};
  const appKey =
    config.appKey && config.appKey.startsWith('base64:')
      ? config.appKey
      : existing.APP_KEY && existing.APP_KEY.startsWith('base64:')
        ? existing.APP_KEY
      : 'base64:' + crypto.randomBytes(32).toString('base64');
  if (config.appKey !== appKey) {
    config.appKey = appKey;
    saveConfig();
  }
  const url = `http://127.0.0.1:${port}`;

  const defaults = {
    APP_NAME: 'Peminjaman Barang PNP',
    APP_ENV: 'production',
    APP_KEY: appKey,
    APP_DEBUG: 'false',
    APP_URL: url,
    FRONTEND_URL: url,
    APP_LOCALE: 'en',
    APP_FALLBACK_LOCALE: 'en',
    APP_FAKER_LOCALE: 'en_US',
    APP_MAINTENANCE_DRIVER: 'file',
    BCRYPT_ROUNDS: '10',
    LOG_CHANNEL: 'stack',
    LOG_STACK: 'single',
    LOG_DEPRECATIONS_CHANNEL: 'null',
    LOG_LEVEL: 'warning',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databaseFile,
    SESSION_DRIVER: 'database',
    SESSION_LIFETIME: '120',
    SESSION_ENCRYPT: 'false',
    SESSION_PATH: '/',
    SESSION_DOMAIN: 'null',
    BROADCAST_CONNECTION: 'log',
    FILESYSTEM_DISK: 'local',
    QUEUE_CONNECTION: 'database',
    CACHE_STORE: 'database',
    MAIL_MAILER: 'log',
    MAIL_HOST: 'smtp-relay.brevo.com',
    MAIL_PORT: '587',
    MAIL_USERNAME: '',
    MAIL_PASSWORD: '',
    MAIL_FROM_ADDRESS: 'no-reply@pnp.local',
    MAIL_FROM_NAME: 'Peminjaman Barang PNP',
    VITE_APP_NAME: 'Peminjaman Barang PNP',
  };

  // Urutan prioritas: default < .env lama (kredensial bertahan) < wizard mail < kunci pin.
  return {
    ...defaults,
    ...existing,
    ...mailEnv(config.mail),
    APP_KEY: appKey,
    APP_URL: url,
    FRONTEND_URL: url,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databaseFile,
  };
}

/* --------------------------------------------------------- runtime backend */

function envFilter(rel) {
  if (rel === '') return true;
  if (rel === '.env' || rel === 'public/storage' || rel === '.git') return false;
  if (rel.startsWith('node_modules')) return false;
  if (rel.startsWith('tests')) return false;
  if (rel === 'database/database.sqlite') return false;
  // Direktori kerja storage ikut, tapi isinya tidak
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
}

/** Mode terinstal: salin template backend ke folder AppData agar writable. */
function ensureRuntimeBackend() {
  if (isDev) return;
  const marker = path.join(runtimeBackend, '.desktop-template-version');
  let current = null;
  try {
    current = fs.readFileSync(marker, 'utf8').trim();
  } catch {
    /* belum ada */
  }
  if (current === TEMPLATE_VERSION && fs.existsSync(path.join(runtimeBackend, 'artisan'))) return;

  fs.rmSync(path.join(userDataDir, 'app'), { recursive: true, force: true });
  fs.mkdirSync(path.dirname(runtimeBackend), { recursive: true });
  fs.cpSync(backendTemplate, runtimeBackend, {
    recursive: true,
    filter: (src) => envFilter(path.relative(backendTemplate, src).replace(/\\/g, '/')),
  });
  fs.writeFileSync(marker, TEMPLATE_VERSION, 'utf8');
}

function validateInstalledResources() {
  if (isDev) return;
  const required = [
    [phpBin, 'PHP portable'],
    [path.join(backendTemplate, 'artisan'), 'backend Laravel'],
    [path.join(backendTemplate, 'config', 'view.php'), 'konfigurasi view Laravel'],
    [path.join(frontendDist, 'index.html'), 'frontend'],
  ];
  const missing = required.filter(([target]) => !fs.existsSync(target));
  if (missing.length) {
    throw new Error(
      `Paket aplikasi tidak lengkap di drive ini (${process.resourcesPath}).\n` +
        missing.map(([, label]) => `- ${label}`).join('\n') +
        '\nSilakan instal ulang dari installer terbaru.',
    );
  }
}

function ensureWritableDirectories() {
  for (const relative of [
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'storage/app/private',
    'storage/app/public',
    'bootstrap/cache',
  ]) {
    fs.mkdirSync(path.join(runtimeBackend, relative), { recursive: true });
  }
}

/* ------------------------------------------------------------- proses PHP */

function phpEnv(extra = {}) {
  const desktopEnv = !isDev ? buildDesktopEnv(backendPort) : {};
  const base = {
    ...process.env,
    ...desktopEnv,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databaseFile,
    DESKTOP_FRONTEND_DIST: frontendDist,
    DESKTOP_API_KEY: config.desktopKey || '',
    ...mailEnv(config.mail),
  };
  if (backendPort) {
    base.APP_URL = `http://127.0.0.1:${backendPort}`;
    base.FRONTEND_URL = base.APP_URL;
  }
  if (tunnelUrl) {
    base.PUBLIC_APP_URL = tunnelUrl;
  }
  return { ...base, ...extra };
}

function runArtisan(args, { allowFailure = false } = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(phpBin, ['artisan', ...args], {
      cwd: runtimeBackend,
      env: phpEnv(),
      windowsHide: true,
    });
    let out = '';
    let err = '';
    child.stdout.on('data', (d) => {
      out += d;
    });
    child.stderr.on('data', (d) => {
      err += d;
    });
    child.on('error', reject);
    child.on('exit', (code) => {
      if (code === 0 || allowFailure) resolve({ code, out, err });
      else reject(new Error(`artisan ${args.join(' ')} gagal (exit ${code}):\n${(err || out).slice(-2000)}`));
    });
  });
}

function pickPort(preferred) {
  return new Promise((resolve, reject) => {
    const tryPort = (port, attempts) => {
      const srv = net.createServer();
      srv.unref();
      srv.on('error', () => {
        if (attempts <= 0) return reject(new Error('Tidak menemukan port bebas.'));
        tryPort(port + 1, attempts - 1);
      });
      srv.listen(port, '127.0.0.1', () => srv.close(() => resolve(port)));
    };
    tryPort(Number(preferred) || PREFERRED_PORT, 60);
  });
}

function startPhpServer() {
  return new Promise((resolve, reject) => {
    phpServerError = '';
    phpServer = spawn(
      phpBin,
      ['-S', `127.0.0.1:${backendPort}`, '-t', 'public', 'desktop-router.php'],
      { cwd: runtimeBackend, env: phpEnv(), windowsHide: true },
    );
    let started = false;
    phpServer.stderr.on('data', (d) => {
      phpServerError += d;
    });
    phpServer.once('error', (e) => {
      if (!started) {
        started = true;
        reject(e);
      }
    });
    // Beri jeda singkat; health-check berikutnya yang memastikan kesiapan penuh.
    setTimeout(() => {
      if (!started) {
        started = true;
        resolve();
      }
    }, 700);
    phpServer.on('exit', (code) => {
      phpServer = null;
      if (!quitting && bootSucceeded && !restartingBackend) {
        dialog.showErrorBox(
          'Server Backend Berhenti',
          `Proses PHP lokal berhenti tanpa sengaja (exit code ${code}).\nSilakan buka kembali aplikasi.`,
        );
        app.quit();
      }
    });
  });
}

function waitHealth(timeoutMs = 60000) {
  const url = `http://127.0.0.1:${backendPort}/up`;
  const start = Date.now();
  return new Promise((resolve, reject) => {
    const probe = () => {
      const req = http.get(url, { timeout: 3000 }, (res) => {
        res.resume();
        if (res.statusCode && res.statusCode < 500) return resolve();
        retry();
      });
      req.on('error', retry);
      req.on('timeout', () => {
        req.destroy();
        retry();
      });
    };
    const retry = () => {
      if (Date.now() - start > timeoutMs) {
        const detail = phpServerError.trim() ? `\n\nOutput server:\n${phpServerError.trim().slice(-800)}` : '';
        return reject(new Error(`Server backend tidak merespons (timeout).${detail}`));
      }
      setTimeout(probe, 400);
    };
    probe();
  });
}

function startQueueWorker() {
  if (quitting) return;
  queueWorker = spawn(phpBin, ['artisan', 'queue:work', '--tries=5', '--backoff=30', '--sleep=3'], {
    cwd: runtimeBackend,
    env: phpEnv(),
    windowsHide: true,
  });
  queueWorker.on('exit', () => {
    queueWorker = null;
    if (!quitting && bootSucceeded && !restartingBackend) setTimeout(startQueueWorker, 3000);
  });
}

function stopChild(child) {
  return new Promise((resolve) => {
    if (!child || !child.pid) return resolve();
    if (child.exitCode !== null || child.signalCode !== null) return resolve();
    child.once('exit', resolve);
    killChild(child);
    setTimeout(resolve, 3000);
  });
}

async function restartBackend() {
  restartingBackend = true;
  const oldQueueWorker = queueWorker;
  const oldPhpServer = phpServer;
  queueWorker = null;
  phpServer = null;
  await Promise.all([stopChild(oldQueueWorker), stopChild(oldPhpServer)]);
  await startPhpServer();
  await waitHealth();
  restartingBackend = false;
  startQueueWorker();
}

function killChild(child) {
  if (!child || !child.pid) return;
  try {
    if (process.platform === 'win32') {
      spawn('taskkill', ['/PID', String(child.pid), '/T', '/F'], { windowsHide: true });
    } else {
      child.kill();
    }
  } catch {
    /* biarkan */
  }
}

/* ------------------------------------------------- tunnel internet publik */

/**
 * Saat kompetur petugas terhubung ke internet, jalankan Cloudflare Quick
 * Tunnel (cloudflared) sehingga server lokal mendapat URL publik sementara
 * (https://xxxx.trycloudflare.com). URL ini dipakai sebagai tautan unduh
 * bukti peminjaman pada email. Tidak perlu akun, VPS, maupun hosting.
 */

function publicUrlFile() {
  return path.join(runtimeBackend, 'storage', 'app', 'desktop-public-url.txt');
}

function persistPublicUrl(url) {
  try {
    if (url) {
      fs.mkdirSync(path.dirname(publicUrlFile()), { recursive: true });
      fs.writeFileSync(publicUrlFile(), url + '\n', 'utf8');
    } else {
      fs.rmSync(publicUrlFile(), { force: true });
    }
  } catch {
    /* abaikan */
  }
}

function captureTunnelUrl(chunk) {
  const match = /https:\/\/[a-z0-9][a-z0-9-]*\.trycloudflare\.com/i.exec(String(chunk));
  if (!match || match[0] === tunnelUrl) return;
  tunnelUrl = match[0];
  persistPublicUrl(tunnelUrl);
}

function startTunnel() {
  if (quitting || tunnelProcess || tunnelUrl) return;
  if (!fs.existsSync(cloudflaredBin)) return;
  try {
    tunnelProcess = spawn(
      cloudflaredBin,
      ['tunnel', '--no-autoupdate', '--url', `http://127.0.0.1:${backendPort}`],
      { windowsHide: true },
    );
  } catch {
    tunnelProcess = null;
    return;
  }
  tunnelProcess.stdout.on('data', (d) => captureTunnelUrl(d));
  tunnelProcess.stderr.on('data', (d) => captureTunnelUrl(d));
  tunnelProcess.on('error', () => {
    tunnelProcess = null;
  });
  tunnelProcess.on('exit', () => {
    tunnelProcess = null;
    if (tunnelUrl) {
      tunnelUrl = null;
      persistPublicUrl(null);
    }
  });
}

/** Mulai tunnel + ulangi otomatis bila offline/terputus. */
function startTunnelSupervisor() {
  if (!fs.existsSync(cloudflaredBin)) return;
  startTunnel();
  if (tunnelRetryTimer) return;
  tunnelRetryTimer = setInterval(() => {
    if (quitting) return;
    if (!tunnelUrl) startTunnel();
  }, 30000);
}

function stopTunnel() {
  if (tunnelRetryTimer) {
    clearInterval(tunnelRetryTimer);
    tunnelRetryTimer = null;
  }
  if (tunnelProcess) {
    killChild(tunnelProcess);
    tunnelProcess = null;
  }
  tunnelUrl = null;
  persistPublicUrl(null);
}

/* ------------------------------------------------------------- auto update */

/**
 * Pembaruan otomatis dari GitHub Releases (electron-updater).
 *
 * Alur:
 *   1. Saat app dibuka, periksa pembaruan di latar belakang (ulangi setiap 4 jam).
 *   2. Bila versi baru tersedia -> diunduh otomatis di latar belakang.
 *   3. Bila download selesai -> popup: "Restart Sekarang / Nanti".
 *   4. Restart -> quitAndInstall(); Nanti -> instal terjadi otomatis saat app ditutup
 *      (autoInstallOnAppQuit). Data dan setup aplikasi tetap bertahan.
 *
 * Versi baru harus publikasi sebagai GitHub Release (file .exe + latest.yml +
 * .blockmap) agar bisa dideteksi oleh versi lama yang sudah terinstal.
 */

function sendUpdateState() {
  try {
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.webContents.send('update:state-push', updateState);
    }
  } catch {
    /* jendela belum siap */
  }
}

function setUpdateState(patch) {
  const wasReady = updateState.state === 'ready';
  updateState = { ...updateState, ...patch };
  sendUpdateState();
  const isReady = updateState.state === 'ready';
  if (wasReady !== isReady && !isDev) {
    // Menu "Instal Pembaruan" berubah disabled <-> enabled bila state siap.
    try {
      buildMenu();
    } catch {
      /* menu belum dibangun */
    }
  }
}

function logUpdate(message) {
  try {
    fs.appendFileSync(path.join(userDataDir, 'update.log'), `[${new Date().toISOString()}] ${message}\n`, 'utf8');
  } catch {
    /* abaikan */
  }
}

function installUpdate() {
  if (quitting || !autoUpdater) return;
  try {
    autoUpdater.quitAndInstall();
  } catch (error) {
    dialog.showErrorBox('Gagal Menginstal Pembaruan', String(error && error.message ? error.message : error));
  }
}

function promptUpdateReady(version) {
  if (updatePromptOpen || quitting) return;
  updatePromptOpen = true;
  dialog
    .showMessageBox({
      type: 'question',
      title: 'Pembaruan Siap — Peminjaman Barang PNP',
      message: `Versi baru ${version || ''} sudah diunduh di latar belakang.`,
      detail:
        'Restart aplikasi sekarang untuk menginstal pembaruan? Instalasi berjalan ' +
        'otomatis; setelah selesai aplikasi buka kembali. Bila memilih Nanti, ' +
        'pembaruan diinstal saat aplikasi ditutup.',
      buttons: ['Restart Sekarang', 'Nanti'],
      defaultButton: 1,
      cancelId: 1,
      noLink: true,
    })
    .then((choice) => {
      updatePromptOpen = false;
      if (choice === 0) installUpdate();
    })
    .catch(() => {
      updatePromptOpen = false;
    });
}

function configureAutoUpdater() {
  if (isDev || !autoUpdater) return;
  autoUpdater.autoDownload = true; // pengunduh di latar belakang
  autoUpdater.autoInstallOnAppQuit = true; // instal otomatis bila app ditutup & sudah unduh

  autoUpdater.on('checking-for-update', () => {
    setUpdateState({ state: 'checking', version: null, percent: 0, message: '' });
  });
  autoUpdater.on('update-available', (info) => {
    const version = info && info.version ? info.version : '';
    setUpdateState({ state: 'downloading', version, percent: 0, message: '' });
    logUpdate(`Pembaruan ${version} tersedia — pengunduh di latar belakang.`);
  });
  autoUpdater.on('update-not-available', () => {
    setUpdateState({ state: 'up-to-date', version: null, percent: 0, message: '' });
  });
  autoUpdater.on('download-progress', (progress) => {
    const percent = progress && typeof progress.percent === 'number' ? Math.round(progress.percent) : updateState.percent;
    setUpdateState({ state: 'downloading', percent });
  });
  autoUpdater.on('update-downloaded', (info) => {
    const version = info && info.version ? info.version : '';
    setUpdateState({ state: 'ready', version, percent: 100, message: '' });
    logUpdate(`Pembaruan ${version} siap diinstal.`);
    promptUpdateReady(version);
  });
  autoUpdater.on('error', (error) => {
    const message = String(error && error.message ? error.message : error);
    setUpdateState({ state: 'error', message });
    logUpdate(`Periksa pembaruan gagal: ${message}`);
  });
}

async function checkForUpdates({ manual = false } = {}) {
  if (isDev || !autoUpdater) {
    if (manual) {
      await dialog.showMessageBox({
        type: 'info',
        title: 'Periksa Pembaruan',
        message: 'Periksa pembaruan otomatis hanya aktif pada versi aplikasi yang terinstal.',
        detail: 'Mode pengembangan/portable tidak mendukung fitur pembaruan otomatis.',
        buttons: ['OK'],
      });
    }
    return;
  }
  if (updateCheckInProgress) return;
  updateCheckInProgress = true;
  setUpdateState({ state: 'checking' });
  try {
    const result = await autoUpdater.checkForUpdates();
    // result null = sudah terbaru; truthy = pembaruan tersedia (bila autoDownload
    // aktif, event update-downloaded sudah membuka prompt restart sendiri).
    if (manual && !result) {
      await dialog.showMessageBox({
        type: 'info',
        title: 'Periksa Pembaruan',
        message: `Aplikasi sudah versi terbaru (${app.getVersion()}).`,
        buttons: ['OK'],
      });
    }
  } catch (error) {
    const message = String(error && error.message ? error.message : error);
    setUpdateState({ state: 'error', message });
    logUpdate(`Periksa pembaruan gagal: ${message}`);
    if (manual) {
      await dialog.showMessageBox({
        type: 'warning',
        title: 'Periksa Pembaruan',
        message: 'Gagal periksa pembaruan.',
        detail: message,
        buttons: ['OK'],
      });
    }
  } finally {
    updateCheckInProgress = false;
  }
}

function startUpdateSupervisor() {
  if (isDev || !autoUpdater) return;
  // Periksa pertama beberapa saat setelah aplikasi siap (server lokal + email
  // dulu), lalu secara periodik setiap 4 jam di latar belakang. Saat komputer
  // offline, error didengono silent dan periksa berikutnya tetap dijalankan.
  setTimeout(() => checkForUpdates(), 20000);
  updateCheckTimer = setInterval(() => checkForUpdates(), 4 * 60 * 60 * 1000);
}

function stopUpdateSupervisor() {
  if (updateCheckTimer) {
    clearInterval(updateCheckTimer);
    updateCheckTimer = null;
  }
}

/* --------------------------------------------------------------- jendela */

function iconPath() {
  const p = path.join(__dirname, 'build', 'icon.png');
  return fs.existsSync(p) ? p : undefined;
}

function createSplash() {
  splashWindow = new BrowserWindow({
    width: 440,
    height: 320,
    frame: false,
    resizable: false,
    alwaysOnTop: true,
    skipTaskbar: true,
    backgroundColor: '#ffffff',
    icon: iconPath(),
    webPreferences: { contextIsolation: true, nodeIntegration: false },
  });
  splashWindow.loadFile('splash.html');
  return splashWindow;
}

function closeSplash() {
  if (splashWindow && !splashWindow.isDestroyed()) splashWindow.close();
  splashWindow = null;
}

function createSetupWindow() {
  setupWindow = new BrowserWindow({
    width: 620,
    height: 780,
    minWidth: 540,
    minHeight: 660,
    title: 'Konfigurasi Awal — ' + APP_TITLE,
    show: false,
    backgroundColor: '#f1f5f9',
    icon: iconPath(),
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });
  setupWindow.loadFile('setup.html');
  setupWindow.once('ready-to-show', () => setupWindow.show());
  setupWindow.on('closed', () => {
    setupWindow = null;
    if (bootSucceeded && !mainWindow && !quitting) openMainWindow();
  });
  return setupWindow;
}

function openMainWindow() {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.show();
    return;
  }
  mainWindow = new BrowserWindow({
    width: 1360,
    height: 880,
    minWidth: 1100,
    minHeight: 700,
    title: APP_TITLE,
    show: false,
    backgroundColor: '#f8fafc',
    icon: iconPath(),
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      spellcheck: false,
    },
  });

  mainWindow.loadURL(`http://127.0.0.1:${backendPort}`);

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (/^https?:/i.test(url)) shell.openExternal(url);
    return { action: 'deny' };
  });
  mainWindow.webContents.on('will-navigate', (event, url) => {
    const allowed = `http://127.0.0.1:${backendPort}`;
    if (!url.startsWith(allowed)) {
      event.preventDefault();
      if (/^https?:/i.test(url)) shell.openExternal(url);
    }
  });

  mainWindow.once('ready-to-show', () => {
    closeSplash();
    mainWindow.show();
    mainWindow.focus();
  });
  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

/* ------------------------------------------------------------- wizard IPC */

function applyMailSettings(payload) {
  config.mail = {
    useSmtp: Boolean(payload && payload.useSmtp),
    host: payload ? payload.host : '',
    port: payload ? payload.port : '587',
    username: payload ? payload.username : '',
    password: payload ? payload.password : '',
    fromAddress: payload ? payload.fromAddress : '',
    fromName: payload ? payload.fromName : '',
  };
  saveConfig();
}

function postJson(pathname, body) {
  return new Promise((resolve, reject) => {
    const data = Buffer.from(JSON.stringify(body || {}), 'utf8');
    const req = http.request(
      {
        hostname: '127.0.0.1',
        port: backendPort,
        path: pathname,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': data.length,
          Accept: 'application/json',
          'X-Desktop-Key': config.desktopKey || '',
        },
        timeout: 45000,
      },
      (res) => {
        let raw = '';
        res.on('data', (c) => {
          raw += c;
        });
        res.on('end', () => {
          try {
            resolve({ status: res.statusCode, json: JSON.parse(raw) });
          } catch {
            reject(new Error(`Respons tidak valid (${res.statusCode}): ${raw.slice(0, 300)}`));
          }
        });
      },
    );
    req.on('error', reject);
    req.on('timeout', () => {
      req.destroy(new Error('Waktu tunggu habis saat menghubungi server lokal.'));
    });
    req.write(data);
    req.end();
  });
}

function registerIpc() {
  ipcMain.handle('file:save-pdf', async (_event, data) => {
    try {
      if (!data || !Array.isArray(data.bytes) || !data.filename) {
        return { ok: false, message: 'Data file tidak valid.' };
      }
      const safeName = String(data.filename).replace(/[<>:"/\\|?*]/g, '-');
      const result = await dialog.showSaveDialog({
        title: 'Simpan dokumen PDF',
        defaultPath: path.join(app.getPath('downloads'), safeName),
        filters: [{ name: 'Dokumen PDF', extensions: ['pdf'] }],
      });
      if (result.canceled || !result.filePath) return { ok: false, canceled: true };
      fs.writeFileSync(result.filePath, Buffer.from(data.bytes));
      return { ok: true, filePath: result.filePath };
    } catch (error) {
      return { ok: false, message: String(error && error.message ? error.message : error) };
    }
  });

  ipcMain.handle('report:preview-pdf', async (_event, data) => {
    try {
      if (!data || !Array.isArray(data.bytes)) {
        return { ok: false, message: 'Data PDF laporan tidak valid.' };
      }
      const filePath = path.join(app.getPath('temp'), `laporan-peminjaman-${Date.now()}.pdf`);
      fs.writeFileSync(filePath, Buffer.from(data.bytes));
      const openError = await shell.openPath(filePath);
      return openError ? { ok: false, message: openError } : { ok: true };
    } catch (error) {
      return { ok: false, message: String(error && error.message ? error.message : error) };
    }
  });

  ipcMain.handle('report:print', (event) => new Promise((resolve) => {
    const window = BrowserWindow.fromWebContents(event.sender);
    if (!window || window.isDestroyed()) {
      resolve({ ok: false, message: 'Jendela aplikasi tidak tersedia.' });
      return;
    }

    window.webContents.print(
      { silent: false, printBackground: true, margins: { marginType: 'default' } },
      (success, failureReason) => resolve({
        ok: success,
        message: success ? '' : failureReason || 'Dialog cetak tidak dapat dibuka.',
      }),
    );
  }));

  ipcMain.handle('setup:open', () => {
    if (setupWindow && !setupWindow.isDestroyed()) {
      setupWindow.focus();
    } else {
      createSetupWindow();
    }
    return { ok: true };
  });

  ipcMain.handle('setup:get', () => {
    const env = readEnvFile(runtimeBackend);
    const mail = config.mail || {};
    return {
      configured: Boolean(config.mail && config.mail.useSmtp && config.mail.username),
      defaults: {
        host: mail.host || env.MAIL_HOST || 'smtp-relay.brevo.com',
        port: mail.port || env.MAIL_PORT || '587',
        username: mail.username || env.MAIL_USERNAME || '',
        password: mail.password || env.MAIL_PASSWORD || '',
        fromAddress: mail.fromAddress || env.MAIL_FROM_ADDRESS || '',
        fromName: mail.fromName || env.MAIL_FROM_NAME || 'Peminjaman Barang PNP',
      },
    };
  });

  ipcMain.handle('setup:save', (_event, payload) => {
    return (async () => {
      try {
      applyMailSettings(payload);
      await restartBackend();
      config.setupDone = true;
      saveConfig();
      return { ok: true };
      } catch (e) {
        return { ok: false, message: String(e && e.message ? e.message : e) };
      }
    })();
  });

  ipcMain.handle('setup:test', async (_event, payload) => {
    try {
      applyMailSettings({ ...payload, useSmtp: true });
      await restartBackend();
      const { json } = await postJson('/api/desktop/mail-test', { to: payload.testTo });
      return { ok: Boolean(json && json.ok), message: json && json.message ? json.message : 'Respons tidak dikenal.' };
    } catch (e) {
      return { ok: false, message: String(e && e.message ? e.message : e) };
    }
  });

  ipcMain.handle('setup:skip', () => {
    config.setupDone = true;
    saveConfig();
    return { ok: true };
  });

  ipcMain.handle('update:get-state', () => ({ ...updateState }));
  ipcMain.handle('update:check', () => {
    checkForUpdates({ manual: true });
    return { ok: true };
  });
}

/* ------------------------------------------------------------------ menu */

function buildMenu() {
  const template = [
    {
      label: 'Aplikasi',
      submenu: [
        {
          label: 'Pengaturan Email…',
          accelerator: 'CmdOrCtrl+E',
          click: () => {
            if (setupWindow && !setupWindow.isDestroyed()) {
              setupWindow.focus();
              return;
            }
            createSetupWindow();
          },
        },
        { type: 'separator' },
        {
          label: 'Periksa Pembaruan…',
          click: () => checkForUpdates({ manual: true }),
        },
        {
          label: updateState.state === 'ready' ? 'Instal Pembaruan Siap…' : 'Instal Pembaruan…',
          enabled: updateState.state === 'ready',
          click: () => {
            if (updateState.state === 'ready') installUpdate();
          },
        },
        { type: 'separator' },
        {
          label: 'Muat Ulang',
          accelerator: 'CmdOrCtrl+R',
          click: () => {
            if (mainWindow) mainWindow.reload();
          },
        },
        {
          label: 'Keluar',
          accelerator: 'Alt+F4',
          click: () => app.quit(),
        },
      ],
    },
    {
      label: 'Tampilan',
      submenu: [
        { role: 'resetZoom', label: 'Ukuran Normal' },
        { role: 'zoomIn', label: 'Perbesar' },
        { role: 'zoomOut', label: 'Perkecil' },
        { type: 'separator' },
        { role: 'togglefullscreen', label: 'Layar Penuh' },
        ...(isDev ? [{ role: 'toggleDevTools', label: 'Developer Tools' }] : []),
      ],
    },
  ];
  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

/* ------------------------------------------------------------------- boot */

async function boot() {
  loadConfig();
  configureAutoUpdater();
  createSplash();

  try {
    validateInstalledResources();
    await ensureRuntimeBackend();
    ensureWritableDirectories();

    backendPort = await pickPort(config.preferredPort);
    config.preferredPort = backendPort;
    saveConfig();

    if (!fs.existsSync(databaseFile)) fs.writeFileSync(databaseFile, '');
    // File SQLite bisa sudah ada walau proses instalasi sebelumnya terhenti
    // sebelum migrasi selesai, jadi selalu sinkronkan schema saat boot.
    await runArtisan(['migrate', '--force']);
    await runArtisan(['db:seed', '--force']);

    await runArtisan(['storage:link'], { allowFailure: true });
    await startPhpServer();
    await waitHealth();

    bootSucceeded = true;
    startQueueWorker();
    startTunnelSupervisor();
    startUpdateSupervisor();

    if (!config.setupDone) createSetupWindow();
    else openMainWindow();
  } catch (e) {
    closeSplash();
    dialog.showErrorBox(
      'Aplikasi Gagal Dimulai',
      String(e && e.message ? e.message : e) + `\n\nLokasi data: ${userDataDir}`,
    );
    app.quit();
  }
}

/* -------------------------------------------------------------- lifecycle */

const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
} else {
  app.on('second-instance', () => {
    const win = mainWindow || setupWindow || splashWindow;
    if (win && !win.isDestroyed()) {
      if (win.isMinimized()) win.restore();
      win.focus();
    }
  });

  app.whenReady().then(() => {
    registerIpc();
    buildMenu();
    boot();
  });
}

app.on('window-all-closed', () => {
  app.quit();
});

app.on('before-quit', () => {
  quitting = true;
  stopUpdateSupervisor();
  stopTunnel();
  killChild(queueWorker);
  killChild(phpServer);
});





