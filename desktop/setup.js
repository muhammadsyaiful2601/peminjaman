'use strict';

const $ = (id) => document.getElementById(id);
const status = $('status');

// Logo bawaan aplikasi (dipakai bila pengguna belum memilih logo sendiri).
const DEFAULT_LOGO = 'assets/logo-pnp.svg';
// Batas ukuran sama dengan validasi di backend (3 MB).
const MAX_LOGO_BYTES = 3 * 1024 * 1024;

// Logo yang sedang aktif di server (untuk dibatalkan / dipulihkan).
let savedLogoUrl = '';
// Data URL logo baru yang akan dikirim saat menyimpan (kosong = tidak diubah).
let pendingLogo = '';

function showStatus(kind, message) {
  status.className = kind;
  status.textContent = message;
}

function mailPayload() {
  return {
    useSmtp: true,
    host: $('host').value.trim(),
    port: $('port').value.trim() || '587',
    username: $('username').value.trim(),
    password: $('password').value,
    fromAddress: $('fromAddress').value.trim(),
    fromName: $('fromName').value.trim(),
    testTo: $('testTo').value.trim(),
  };
}

function applyLogoPreview(src) {
  const target = src || DEFAULT_LOGO;
  $('logoPreview').src = target;
  $('headLogo').src = target;
}

function setBusy(busy) {
  $('test').disabled = busy;
  $('save').disabled = busy;
  $('skip').disabled = busy;
  $('test').textContent = busy ? 'Mengirim…' : 'Kirim Email Tes';
  $('logoFile').disabled = busy;
  $('logoReset').disabled = busy;
  $('appName').disabled = busy;
}

async function init() {
  if (!window.desktop) {
    showStatus('err', 'Bridge desktop tidak tersedia. Jalankan aplikasi melalui Electron.');
    return;
  }

  const data = await window.desktop.getSetup();
  const mail = data.defaults || {};
  $('host').value = mail.host || '';
  $('port').value = mail.port || '587';
  $('username').value = mail.username || '';
  $('password').value = mail.password || '';
  $('fromAddress').value = mail.fromAddress || '';
  $('fromName').value = mail.fromName || 'Peminjaman Barang PNP';

  // Identitas aplikasi: nama & logo aktif (bawaan atau hasil pengaturan lalu).
  const app = data.app || {};
  const appName = app.name || 'Sistem Peminjaman Barang';
  $('appName').value = appName;
  $('headSubtitle').textContent = appName;

  savedLogoUrl = app.logoUrl || '';
  applyLogoPreview(savedLogoUrl);
}

// Nama aplikasi di judul kartu ikut berubah saat pengguna mengetik.
$('appName').addEventListener('input', (event) => {
  $('headSubtitle').textContent = event.target.value.trim() || 'Sistem Peminjaman Barang';
});

// Logo dipilih dari berkas lokal: dibaca sebagai data URL lalu dikirim ke
// server lokal saat menyimpan (aplikasi desktop tidak butuh unggah manual).
$('logoFile').addEventListener('change', (event) => {
  const file = event.target.files && event.target.files[0];
  if (!file) return;

  if (file.size > MAX_LOGO_BYTES) {
    event.target.value = '';
    showStatus('err', 'Ukuran logo melebihi 3 MB. Pilih gambar yang lebih kecil.');
    return;
  }

  const reader = new FileReader();
  reader.onload = () => {
    pendingLogo = String(reader.result || '');
    applyLogoPreview(pendingLogo);
    showStatus('ok', 'Logo "' + file.name + '" siap dipakai. Klik "Simpan & Mulai Aplikasi" untuk menerapkan.');
  };
  reader.onerror = () => showStatus('err', 'Gagal membaca berkas logo.');
  reader.readAsDataURL(file);
});

// Batalkan pilihan logo baru -> kembali ke logo yang sedang aktif.
$('logoReset').addEventListener('click', () => {
  $('logoFile').value = '';
  pendingLogo = '';
  applyLogoPreview(savedLogoUrl);
  showStatus('ok', 'Pilihan logo dibatalkan — logo aplikasi tidak diubah.');
});

$('save').addEventListener('click', async () => {
  const appName = $('appName').value.trim();
  if (!appName) {
    showStatus('err', 'Nama aplikasi tidak boleh kosong.');
    return;
  }

  setBusy(true);
  // Nama + logo dikirim bersama konfigurasi email dalam satu penyimpanan.
  const res = await window.desktop.saveSetup({
    ...mailPayload(),
    appName,
    logoBase64: pendingLogo,
  });
  setBusy(false);
  if (!res.ok) {
    showStatus('err', 'Gagal menyimpan: ' + (res.message || 'tidak diketahui'));
    return;
  }
  window.close();
});

$('test').addEventListener('click', async () => {
  const payload = mailPayload();
  if (!payload.testTo || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(payload.testTo)) {
    showStatus('err', 'Isi alamat email tujuan pengiriman tes terlebih dahulu.');
    return;
  }
  setBusy(true);
  showStatus('ok', 'Mengirim email tes… (perlu koneksi internet)');
  const res = await window.desktop.testMail(payload);
  setBusy(false);
  showStatus(res.ok ? 'ok' : 'err', res.message);
});

$('skip').addEventListener('click', async () => {
  await window.desktop.skipSetup();
  window.close();
});

init();
