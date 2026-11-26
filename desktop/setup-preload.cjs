const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('shopSetup', { save: account => ipcRenderer.invoke('setup-account', account), restore: () => ipcRenderer.invoke('setup-restore') });
