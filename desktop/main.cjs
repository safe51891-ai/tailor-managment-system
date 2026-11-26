'use strict';
const { app, BrowserWindow, Menu, dialog, ipcMain } = require('electron');
const path = require('node:path');
const fs = require('node:fs');
const { startBackend, allowedNavigation } = require('./backend.cjs');
const { prepareLocalData, initialize } = require('./local-data.cjs');
const { backupUI } = require('./backup-ui.cjs');
const { createBackup, verifyBackup, restoreBackup } = require('./backup.cjs');
const { ensureActivated } = require('./license-ui.cjs');
let activeLicense = null;
let window = null, backend = null, log = null, quitting = false, restoring = false, local = null, backups = null, backupTimer = null, shutdownDone = false;
function watchBackend() {
    backend.child.on('exit', () => {
        if (!quitting && !restoring) { dialog.showErrorBox('Tailor Shop stopped', 'The local app has stopped. Restart Tailor Shop.'); app.quit(); }
    });
}
async function stopBackend() {
    restoring = true;
    if (backend && backend.child.exitCode === null && backend.child.signalCode === null) {
        await new Promise(resolve => { backend.child.once('exit', resolve); backend.stop(); });
    }
}

async function setupAccount(local) {
    const setup = new BrowserWindow({ title: 'Set up Tailor Shop', width: 650, height: 810, resizable: false, autoHideMenuBar: true,
        backgroundColor: '#f5f3ed', webPreferences: { preload: path.join(__dirname, 'setup-preload.cjs'), nodeIntegration: false, contextIsolation: true, sandbox: true } });
    setup.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
    setup.webContents.on('will-navigate', event => event.preventDefault());
    let creating = false, completed = false;
    return new Promise((resolve, reject) => {
        ipcMain.handle('setup-account', async (event, account) => {
            if (event.sender !== setup.webContents || creating) return { ok: false, error: 'Setup is already in progress.' };
            creating = true;
            try {
                await initialize(local.project, local.env, account);
                completed = true;
                ipcMain.removeHandler('setup-account'); ipcMain.removeHandler('setup-restore');
                resolve();
                setup.close();
                return { ok: true };
            } catch (error) { creating = false; return { ok: false, error: error.message }; }
        });
        ipcMain.handle('setup-restore', async event => {
            if (event.sender !== setup.webContents || creating) return { ok: false, error: 'Setup is already in progress.' };
            creating = true;
            try {
                const selected = await dialog.showOpenDialog(setup, { title: 'Choose a Tailor-Shop-Backup folder', properties: ['openDirectory'] });
                if (selected.canceled) return { ok: false, error: '' };
                const folder = selected.filePaths[0], version = require('./package.json').version;
                const manifest = verifyBackup(folder, version);
                const choice = await dialog.showMessageBox(setup, { type: 'warning', message: 'Restore your shop from this backup?', detail: folder + '\nSaved: ' + new Date(manifest.createdAt).toLocaleString('en-PK', { timeZone: 'Asia/Karachi' }) + '\nYou will use the sign-in account saved in this backup.', buttons: ['Cancel', 'Restore backup'], defaultId: 0, cancelId: 0 });
                if (choice.response !== 1) return { ok: false, error: '' };
                await createBackup(local, path.join(app.getPath('userData'), 'restore-safety-backups'), path.join(app.isPackaged ? process.resourcesPath : __dirname, 'backup.php'), version);
                await restoreBackup(local, folder, version, async () => {
                    const restored = await initialize(local.project, local.env);
                    if (restored.needsSetup) throw new Error('This backup does not contain an owner account.');
                });
                completed = true; ipcMain.removeHandler('setup-account'); ipcMain.removeHandler('setup-restore'); resolve(); setup.close();
                return { ok: true };
            } catch (error) { return { ok: false, error: error.message }; }
            finally { creating = false; }
        });
        setup.on('closed', () => { ipcMain.removeHandler('setup-account'); ipcMain.removeHandler('setup-restore'); if (!completed) reject(new Error('Account setup was cancelled. You can finish it when you reopen the app.')); });
        setup.loadFile(path.join(__dirname, 'setup.html')).catch(reject);
    });
}

if (!app.requestSingleInstanceLock()) {
    app.quit();
} else {
    app.on('second-instance', () => { if (window) { if (window.isMinimized()) window.restore(); window.focus(); } });
    app.whenReady().then(async () => {
        try {
            log = fs.createWriteStream(path.join(app.getPath('userData'), 'desktop.log'), { flags: 'a', mode: 0o600 });
            log.write('\nTailor Shop ' + app.getVersion() + ' startup: ' + new Date().toISOString() + '\n');
            let project = path.resolve(process.env.TAILOR_PROJECT_PATH || path.join(__dirname, '..'));
            let environment = process.env;
            if (app.isPackaged || process.env.TAILOR_SELF_CONTAINED === '1') {
                activeLicense = await ensureActivated(app.getPath('userData'));
                local = prepareLocalData(process.env.TAILOR_RESOURCES_PATH || process.resourcesPath, app.getPath('userData'));
                project = local.project; environment = local.env;
                const result = await initialize(project, environment);
                if (result.needsSetup) await setupAccount(local);
            }
            backend = await startBackend(project, log, environment);
            watchBackend();
            window = new BrowserWindow({
                title: 'Tailor Shop', width: 1440, height: 940, minWidth: 960, minHeight: 680,
                icon: path.join(app.isPackaged ? process.resourcesPath : path.join(__dirname, 'build'), 'icon.png'),
                show: false, backgroundColor: '#f5f3ed', autoHideMenuBar: true,
                webPreferences: { preload: path.join(__dirname, 'app-preload.cjs'), nodeIntegration: false, contextIsolation: true, sandbox: true, devTools: !app.isPackaged }
            });
            if (local) backups = backupUI({ local, userData: app.getPath('userData'), version: require('./package.json').version,
                script: path.join(app.isPackaged ? process.resourcesPath : __dirname, 'backup.php'), dialog, getWindow: () => window,
                stopBackend, migrate: () => initialize(local.project, local.env),
                restartBackend: async () => { backend = await startBackend(project, log, environment); watchBackend(); restoring = false; await window.loadURL(backend.origin); }
            });
            const backupSender = event => window && event.sender === window.webContents && allowedNavigation(event.sender.getURL(), backend.origin);
            const developmentBackupStatus = { enabled: false, automatic: false, error: 'Automatic backups require the self-contained desktop installation. This development app uses MariaDB.' };
            ipcMain.handle('license-status', event => backupSender(event) ? (activeLicense ? { activated: true, customer: activeLicense.customer, licenseId: activeLicense.licenseId, machineCode: activeLicense.machineCode } : { development: true }) : null);
            ipcMain.handle('backup-status', event => backupSender(event) ? (backups ? backups.status() : developmentBackupStatus) : { enabled: false });
            ipcMain.handle('backup-open', event => {
                if (!backupSender(event)) return;
                if (backups) return backups.open();
                return dialog.showMessageBox(window, { title: 'Shop backups', message: 'Backups in the client desktop app', detail: developmentBackupStatus.error + '\nIn the client installation, choose a folder on your SSD or external drive and check Automatic backup. Manual backup and restore are also available.', buttons: ['Close'] });
            });
            ipcMain.handle('backup-automatic', async (event, value) => {
                if (!backupSender(event) || !backups) return { ok: false, status: developmentBackupStatus };
                try { return { ok: true, status: await backups.setAutomatic(value) }; }
                catch (error) { return { ok: false, status: backups.status(), error: error.message }; }
            });
            Menu.setApplicationMenu(Menu.buildFromTemplate([
                ...(process.platform === 'darwin' ? [{ label: 'Tailor Shop', submenu: [{ role: 'about' }, { type: 'separator' }, { role: 'quit' }] }] : []),
                { label: 'File', submenu: [...(backups ? [{ label: 'Backups', click: () => backups.open() }, { type: 'separator' }] : []), { label: 'Print', accelerator: 'CmdOrCtrl+P', click: () => window.webContents.print() }, { type: 'separator' }, { role: 'quit' }] },
                { label: 'Edit', submenu: [{ role: 'undo' }, { role: 'redo' }, { type: 'separator' }, { role: 'cut' }, { role: 'copy' }, { role: 'paste' }, { role: 'selectAll' }] },
                { label: 'View', submenu: [{ role: 'reload' }, { role: 'resetZoom' }, { role: 'zoomIn' }, { role: 'zoomOut' }, { role: 'togglefullscreen' }] }
            ]));
            window.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
            window.webContents.on('will-navigate', (event, url) => { if (!allowedNavigation(url, backend.origin)) event.preventDefault(); });
            window.webContents.session.setPermissionRequestHandler((_contents, _permission, callback) => callback(false));
            window.once('ready-to-show', () => window.show());
            window.on('closed', () => { window = null; });
            await window.loadURL(backend.origin);
            if (backups) {
                await backups.start();
                backupTimer = setInterval(() => backups.run().catch(() => {}), 30 * 60 * 1000);
            }
        } catch (error) {
            if (error.code === 'ACTIVATION_CANCELLED') { app.quit(); return; }
            if (log) log.write(String(error.stack || error) + '\n');
            dialog.showErrorBox('Unable to open Tailor Shop', error.message + '\n\nStartup log: ' + path.join(app.getPath('userData'), 'desktop.log'));
            app.quit();
        }
    });
    app.on('window-all-closed', () => { if (backend || quitting) app.quit(); });
    app.on('before-quit', event => {
        if (shutdownDone) return;
        if (quitting || (backups && backups.isBusy())) { event.preventDefault(); if (!quitting) setTimeout(() => app.quit(), 1000); return; }
        event.preventDefault(); quitting = true;
        if (backupTimer) clearInterval(backupTimer);
        (async () => {
            try { await stopBackend(); if (backups) await backups.runOnClose(); }
            catch (error) { dialog.showErrorBox('Closing backup not saved', 'The app will close. Reconnect the backup drive next time.\n' + error.message); }
            finally { shutdownDone = true; if (log) log.end(); app.quit(); }
        })();
    });
}
