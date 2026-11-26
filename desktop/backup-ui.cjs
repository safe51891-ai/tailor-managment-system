'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { createBackup, verifyBackup, restoreBackup, day } = require('./backup.cjs');
function backupUI({ local, userData, script, version, dialog, getWindow, stopBackend, restartBackend, migrate }) {
    const settingsFile = path.join(userData, 'backup-settings.json');
    let settings = {};
    try { if (fs.existsSync(settingsFile)) settings = JSON.parse(fs.readFileSync(settingsFile, 'utf8')); if (!settings || typeof settings !== 'object' || Array.isArray(settings)) throw new Error('Invalid settings'); }
    catch { settings = {}; settings.error = 'Backup settings could not be read. Choose your backup folder again.'; }
    if (settings.automatic === undefined) settings.automatic = Boolean(settings.folder);
    let busy = false;
    const save = () => fs.writeFileSync(settingsFile, JSON.stringify(settings, null, 2), { mode: 0o600 });
    const automatic = () => settings.automatic === true;
    const status = () => ({ enabled: true, automatic: automatic(), folder: settings.folder || '', lastSuccess: settings.lastSuccess || '', error: settings.error || '' });
    async function run(force = false) {
        if (!settings.folder || busy || (!force && (!automatic() || settings.lastDay === day()))) return;
        busy = true;
        try {
            const backup = await createBackup(local, settings.folder, script, version);
            settings.lastDay = day(); settings.lastSuccess = backup.createdAt; settings.lastBackup = backup.folder; settings.error = ''; save();
            return backup;
        } catch (error) { settings.error = error.message; save(); throw error; }
        finally { busy = false; }
    }
    async function chooseFolder() {
        const selected = await dialog.showOpenDialog(getWindow(), { title: 'Choose daily backup folder on another drive', properties: ['openDirectory', 'createDirectory'] });
        if (selected.canceled) return false;
        if (busy) throw new Error('Wait for the current backup to finish.');
        const previous = settings.folder;
        settings.folder = selected.filePaths[0];
        try { await run(true); }
        catch (error) { settings.folder = previous; save(); throw error; }
        return true;
    }
    async function setAutomatic(value) {
        if (typeof value !== 'boolean') throw new Error('Choose whether automatic backup is on or off.');
        if (value && !settings.folder && !await chooseFolder()) return status();
        settings.automatic = value; save();
        if (value) await run();
        return status();
    }
    async function runOnClose() { if (automatic()) return run(true); }
    async function restore() {
        if (busy) throw new Error('Wait for the current backup to finish.');
        const selected = await dialog.showOpenDialog(getWindow(), { title: 'Choose a Tailor-Shop-Backup folder', properties: ['openDirectory'] });
        if (selected.canceled) return;
        const folder = selected.filePaths[0], manifest = verifyBackup(folder, version);
        const choice = await dialog.showMessageBox(getWindow(), { type: 'warning', title: 'Restore shop backup?', message: 'Replace the current shop records with this backup?', detail: 'Backup: ' + new Date(manifest.createdAt).toLocaleString('en-PK', { timeZone: 'Asia/Karachi' }) + '\nFolder: ' + folder + '\nA safety copy of your current data will be kept. You will sign in again after restoring.', buttons: ['Cancel', 'Restore backup'], defaultId: 0, cancelId: 0 });
        if (choice.response !== 1) return;
        if (busy) throw new Error('Wait for the current backup to finish.');
        busy = true;
        try {
            await stopBackend();
            await createBackup(local, path.join(userData, 'restore-safety-backups'), script, version);
            await restoreBackup(local, folder, version, migrate);
            settings.lastDay = ''; settings.error = ''; save();
        } finally { try { await restartBackend(); } finally { busy = false; } }
        await dialog.showMessageBox(getWindow(), { type: 'info', message: 'Backup restored. Sign in with the account saved in that backup.' });
    }
    async function open() {
        if (busy) return dialog.showMessageBox(getWindow(), { message: 'A backup or restore is in progress. Please wait.' });
        const choice = await dialog.showMessageBox(getWindow(), { title: 'Shop backups', message: 'Daily backup to your chosen drive', detail: 'Automatic backup: ' + (automatic() ? 'On' : 'Off') + '\nFolder: ' + (settings.folder || 'Not selected') + '\nLast successful backup: ' + (settings.lastSuccess ? new Date(settings.lastSuccess).toLocaleString('en-PK', { timeZone: 'Asia/Karachi' }) : 'None') + (settings.error ? '\nLast backup failed: ' + settings.error : '') + '\n\nWhen Automatic backup is checked in the top bar, backup runs once daily while the app is open and again when it closes. Uncheck it to pause automatic backups. Keep the drive connected. Choose a different physical drive to protect against Windows or system-disk failure.', buttons: ['Choose folder', 'Back up now', 'Restore backup', 'Close'], defaultId: 3, cancelId: 3 });
        try {
            if (choice.response === 0) { await chooseFolder(); }
            if (choice.response === 1) {
                if (!settings.folder) await chooseFolder();
                else { const result = await run(true); if (result) await dialog.showMessageBox(getWindow(), { message: 'Backup saved successfully.', detail: result.folder }); }
            }
            if (choice.response === 2) await restore();
        } catch (error) { dialog.showErrorBox('Backup / restore could not finish', error.message); }
    }
    async function start() {
        if (!settings.prompted && !settings.folder) {
            settings.prompted = true; save();
            const choice = await dialog.showMessageBox(getWindow(), { title: 'Protect your shop records', message: 'Choose a folder for daily backups on another drive.', detail: 'Your database and uploaded files will be saved together. You can set this later using Backups.', buttons: ['Choose folder', 'Later'], defaultId: 0, cancelId: 1 });
            if (choice.response === 0) { try { if (await chooseFolder()) await setAutomatic(true); } catch (error) { dialog.showErrorBox('Backup could not finish', error.message); } }
        }
        try { await run(); } catch (error) { dialog.showErrorBox('Daily backup not saved', 'Reconnect your backup drive or choose another folder using Backups.\n' + error.message); }
    }
    return { open, start, run, runOnClose, setAutomatic, status, isBusy: () => busy };
}
module.exports = { backupUI };
