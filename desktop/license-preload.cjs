'use strict';
const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('shopLicense', {
    info: () => ipcRenderer.invoke('license-info'), copy: () => ipcRenderer.invoke('license-copy'),
    saveRequest: () => ipcRenderer.invoke('license-request'), import: () => ipcRenderer.invoke('license-import')
});
