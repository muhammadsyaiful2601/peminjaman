'use strict';

/**
 * Jembatan aman antara halaman (wizard/SPA) dan main process.
 * Tidak ada akses Node langsung ke renderer (contextIsolation + sandbox).
 */
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('desktop', {
  isDesktop: true,
  saveFile: (buffer, filename) => ipcRenderer.invoke('file:save', { buffer, filename }),
  savePdf: (bytes, filename) => ipcRenderer.invoke('file:save-pdf', { bytes: Array.from(bytes), filename }),
  previewReportPdf: (bytes) => ipcRenderer.invoke('report:preview-pdf', { bytes: Array.from(bytes) }),
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
  importStudentsFromCsv: (csvUrl) => ipcRenderer.invoke('students:import-published-csv', csvUrl),
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
