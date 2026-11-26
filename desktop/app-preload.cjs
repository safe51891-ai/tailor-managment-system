const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('shopDesktop', {
    licenseStatus: () => ipcRenderer.invoke('license-status'),
    backupStatus: () => ipcRenderer.invoke('backup-status'),
    openBackups: () => ipcRenderer.invoke('backup-open'),
    setAutomaticBackup: value => ipcRenderer.invoke('backup-automatic', value),
});
