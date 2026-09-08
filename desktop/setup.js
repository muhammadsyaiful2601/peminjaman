'use strict';

const $ = (id) => document.getElementById(id);
const status = $('status');

function showStatus(kind, message) {
  status.className = kind;
  status.textContent = message;
}

function collect() {
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

function setBusy(busy) {
  $('test').disabled = busy;
  $('save').disabled = busy;
  $('skip').disabled = busy;
  $('test').textContent = busy ? 'Mengirim…' : 'Kirim Email Tes';
}

async function init() {
  if (!window.desktop) {
    showStatus('err', 'Bridge desktop tidak tersedia. Jalankan aplikasi melalui Electron.');
    return;
  }
  const data = await window.desktop.getSetup();
  $('host').value = data.defaults.host || '';
  $('port').value = data.defaults.port || '587';
  $('username').value = data.defaults.username || '';
  $('password').value = data.defaults.password || '';
  $('fromAddress').value = data.defaults.fromAddress || '';
  $('fromName').value = data.defaults.fromName || 'Peminjaman Barang PNP';
}

$('save').addEventListener('click', async () => {
  const payload = collect();
  setBusy(true);
  const res = await window.desktop.saveSetup(payload);
  setBusy(false);
  if (!res.ok) {
    showStatus('err', 'Gagal menyimpan: ' + (res.message || 'tidak diketahui'));
    return;
  }
  window.close();
});

$('test').addEventListener('click', async () => {
  const payload = collect();
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
