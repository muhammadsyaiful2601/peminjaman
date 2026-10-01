'use strict';

/**
 * Jembatan aman antara halaman (wizard/SPA) dan main process.
 * Tidak ada akses Node langsung ke renderer (contextIsolation + sandbox).
 */
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('desktop', {
  isDesktop: true,
  // ArrayBuffer dikirim apa adanya (bukan diubah jadi array angka) supaya
  // berkas besar seperti PDF laporan & arsip backup tidak menghabiskan memori
  // saat diserialisasi lewat IPC.
  saveFile: (buffer, filename) => ipcRenderer.invoke('file:save', { buffer, filename }),
  savePdf: (buffer, filename) => ipcRenderer.invoke('file:save-pdf', { buffer, filename }),
  previewReportPdf: (bytes) => ipcRenderer.invoke('report:preview-pdf', { bytes: Array.from(bytes) }),
  printDocument: (html) => ipcRenderer.invoke('document:print', { html }),
  printReport: () => ipcRenderer.invoke('report:print'),
  openEmailSetup: () => ipcRenderer.invoke('setup:open'),
  getSetup: () => ipcRenderer.invoke('setup:get'),
  saveSetup: (payload) => ipcRenderer.invoke('setup:save', payload),
  testMail: (payload) => ipcRenderer.invoke('setup:test', payload),
  skipSetup: () => ipcRenderer.invoke('setup:skip'),
  getMailSettings: () => ipcRenderer.invoke('mail:get'),
  saveMailSettings: (payload) => ipcRenderer.invoke('mail:save', payload),
  testMailSettings: (payload) => ipcRenderer.invoke('mail:test', payload),
  getDesktopKey: () => ipcRenderer.invoke('desktop:get-key'),
  // Dipanggil setelah pemulihan database: migrate + restart server backend
  // agar skema database versi lama langsung dilengkapi tanpa restart aplikasi.
  afterRestore: () => ipcRenderer.invoke('app:after-restore'),
  importStudentsFromCsv: (csvUrl, type) => ipcRenderer.invoke('students:import-published-csv', csvUrl, type),
  getUpdateState: () => ipcRenderer.invoke('update:get-state'),
  checkForUpdates: () => ipcRenderer.invoke('update:check'),
  downloadUpdate: () => ipcRenderer.invoke('update:download'),
  installUpdate: () => ipcRenderer.invoke('update:install'),
  setUpdateChannel: (channel) => ipcRenderer.invoke('update:set-channel', channel),
  onUpdateState: (callback) => {
    const channel = 'update:state-push';
    ipcRenderer.removeAllListeners(channel);
    ipcRenderer.on(channel, (_event, state) => callback(state));
    return () => ipcRenderer.removeAllListeners(channel);
  },
});
