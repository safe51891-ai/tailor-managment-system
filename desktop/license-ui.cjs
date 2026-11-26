'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { BrowserWindow, ipcMain, dialog, clipboard } = require('electron');
const { getMachineCode, licenseStatus, importLicense } = require('./license.cjs');
async function ensureActivated(userData) {
    const publicKey = fs.readFileSync(path.join(__dirname, 'license-public-key.pem'), 'utf8');
    const machineCode = getMachineCode();
    const status = licenseStatus(userData, publicKey, machineCode);
    if (status.activated) return status.license;
    const activation = new BrowserWindow({ title: 'Activate Tailor Shop', width: 700, height: 700, minWidth: 540, minHeight: 640,
        autoHideMenuBar: true, backgroundColor: '#f5f3ed',
        webPreferences: { preload: path.join(__dirname, 'license-preload.cjs'), contextIsolation: true, nodeIntegration: false, sandbox: true } });
    activation.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
    activation.webContents.on('will-navigate', event => event.preventDefault());
    activation.webContents.session.setPermissionRequestHandler((_contents, _permission, callback) => callback(false));
    let done = false, busy = false;
    const channels = ['license-info', 'license-copy', 'license-request', 'license-import'];
    return new Promise((resolve, reject) => {
        const trusted = event => !activation.isDestroyed() && event.sender === activation.webContents;
        ipcMain.handle('license-info', event => trusted(event) ? status : null);
        ipcMain.handle('license-copy', event => { if (trusted(event)) { clipboard.writeText(machineCode); return true; } return false; });
        ipcMain.handle('license-request', async event => {
            if (!trusted(event) || busy) return { ok: false };
            busy = true;
            try {
                const result = await dialog.showSaveDialog(activation, { title: 'Save computer code to send to your app provider', defaultPath: 'Tailor-Shop-Computer-Code.txt', filters: [{ name: 'Computer code', extensions: ['txt'] }] });
                if (result.canceled || !result.filePath) return { ok: false };
                fs.writeFileSync(result.filePath, 'Tailor Shop activation request\nComputer code: ' + machineCode + '\n\nSend this file to your app provider. No shop records are included.\n');
                return { ok: true };
            } catch (error) { return { ok: false, error: error.message }; }
            finally { busy = false; }
        });
        ipcMain.handle('license-import', async event => {
            if (!trusted(event) || busy) return { ok: false };
            busy = true;
            try {
                const result = await dialog.showOpenDialog(activation, { title: 'Choose your activation file', properties: ['openFile'], filters: [{ name: 'Tailor Shop license', extensions: ['lic'] }] });
                if (result.canceled || !result.filePaths[0]) return { ok: false };
                const license = importLicense(result.filePaths[0], userData, publicKey, machineCode);
                done = true;
                resolve(license);
                activation.close();
                return { ok: true };
            } catch (error) { return { ok: false, error: error.message }; }
            finally { busy = false; }
        });
        activation.on('closed', () => {
            channels.forEach(channel => ipcMain.removeHandler(channel));
            if (!done) { const error = new Error('Activation was cancelled.'); error.code = 'ACTIVATION_CANCELLED'; reject(error); }
        });
        activation.loadFile(path.join(__dirname, 'license.html')).catch(error => { activation.destroy(); reject(error); });
    });
}
module.exports = { ensureActivated };
