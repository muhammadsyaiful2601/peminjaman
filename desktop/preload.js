'use strict';

/**
 * Jembatan aman antara halaman (wizard/SPA) dan main process.
 * Tidak ada akses Node langsung ke renderer (contextIsolation + sandbox).
 */
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('desktop', {
  isDesktop: true,
  openEmailSetup: () => ipcRenderer.invoke('setup:open'),
  getSetup: () => ipcRenderer.invoke('setup:get'),
  saveSetup: (payload) => ipcRenderer.invoke('setup:save', payload),
  testMail: (payload) => ipcRenderer.invoke('setup:test', payload),
  skipSetup: () => ipcRenderer.invoke('setup:skip'),
});
