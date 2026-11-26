'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFile } = require('node:child_process');
const { phpBinary, phpArguments } = require('./backend.cjs');
const day = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Karachi', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
const hash = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const inside = (file, directory) => { const relative = path.relative(path.resolve(directory), path.resolve(file)); return relative === '' || (!relative.startsWith('..' + path.sep) && relative !== '..' && !path.isAbsolute(relative)); };
function snapshot(local, target, script) {
    return new Promise((resolve, reject) => execFile(phpBinary(local.env), [...phpArguments(local.env), script, path.join(local.data, 'shop.sqlite'), target], { env: local.env, windowsHide: true }, (error, stdout, stderr) => error ? reject(new Error(stderr || 'Unable to snapshot the database.')) : resolve()));
}
function copyTree(source, target) {
    if (!fs.existsSync(source)) return;
    if (fs.lstatSync(source).isSymbolicLink()) throw new Error('Backup files cannot contain symbolic links.');
    if (fs.statSync(source).isDirectory()) {
        fs.mkdirSync(target, { recursive: true, mode: 0o700 });
        for (const name of fs.readdirSync(source)) copyTree(path.join(source, name), path.join(target, name));
    } else fs.copyFileSync(source, target);
}
function inventory(root, directory = root) {
    let files = [];
    for (const name of fs.readdirSync(directory)) {
        const file = path.join(directory, name);
        if (fs.lstatSync(file).isSymbolicLink()) throw new Error('Backup files cannot contain symbolic links.');
        if (fs.statSync(file).isDirectory()) files.push(...inventory(root, file));
        else files.push({ path: path.relative(root, file).split(path.sep).join('/'), sha256: hash(file) });
    }
    return files;
}
async function createBackup(local, destination, script, version) {
    if (inside(destination, local.data) || inside(destination, local.project)) throw new Error('Choose a backup folder outside the application data folder, preferably on another drive.');
    fs.mkdirSync(destination, { recursive: true });
    const folder = fs.mkdtempSync(path.join(destination, '.tailor-partial-'));
    try {
        await snapshot(local, path.join(folder, 'shop.sqlite'), script);
        copyTree(path.join(local.data, 'app-key'), path.join(folder, 'app-key'));
        fs.chmodSync(path.join(folder, 'app-key'), 0o600);
        copyTree(path.join(local.project, 'public/storage'), path.join(folder, 'public-storage'));
        copyTree(path.join(local.data, 'storage/app/public'), path.join(folder, 'storage-public'));
        const manifest = { format: 'tailor-shop-backup', formatVersion: 1, version, createdAt: new Date().toISOString(), day: day(), files: inventory(folder) };
        fs.writeFileSync(path.join(folder, 'backup.json'), JSON.stringify(manifest, null, 2), { mode: 0o600 });
        const final = path.join(destination, 'Tailor-Shop-Backup-' + day() + '-' + Date.now() + '-' + crypto.randomBytes(3).toString('hex'));
        fs.renameSync(folder, final);
        return { folder: final, ...manifest };
    } catch (error) { fs.rmSync(folder, { recursive: true, force: true }); throw error; }
}
function verifyBackup(folder, version) {
    if (fs.lstatSync(folder).isSymbolicLink()) throw new Error('Select a real backup folder.');
    const manifestPath = path.join(folder, 'backup.json');
    if (fs.lstatSync(manifestPath).isSymbolicLink()) throw new Error('Invalid backup manifest.');
    const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    if (manifest.format !== 'tailor-shop-backup' || manifest.formatVersion !== 1 || !Array.isArray(manifest.files)) throw new Error('This is not a complete Tailor Shop backup.');
    const numbers = value => /^\d+\.\d+\.\d+$/.test(value || '') ? value.split('.').map(Number) : null;
    const from = numbers(manifest.version), current = numbers(version);
    if (!from || !current || from.some((n, i) => from.slice(0, i).every((v, j) => v === current[j]) && n > current[i])) throw new Error('Install the same or a newer app version before restoring this backup.');
    const actual = inventory(folder).filter(file => file.path !== 'backup.json');
    const paths = new Set();
    for (const file of manifest.files) {
        if (typeof file.path !== 'string' || !/^(shop\.sqlite|app-key|(?:public-storage|storage-public)\/.+)$/.test(file.path) || file.path.split('/').some(part => part === '..' || part === '.' || !part || part.includes('\\') || part.includes(':')) || paths.has(file.path)) throw new Error('Invalid file in backup.');
        paths.add(file.path);
        const found = actual.find(item => item.path === file.path);
        if (!found || found.sha256 !== file.sha256) throw new Error('Backup is incomplete or damaged: ' + file.path);
    }
    if (!paths.has('shop.sqlite') || !paths.has('app-key') || actual.length !== paths.size) throw new Error('Backup files are missing or unexpected.');
    if (!/^base64:[A-Za-z0-9+/]{43}=$/.test(fs.readFileSync(path.join(folder, 'app-key'), 'utf8').trim())) throw new Error('Backup encryption key is invalid.');
    return manifest;
}
async function restoreBackup(local, folder, version, migrate) {
    verifyBackup(folder, version);
    const staging = fs.mkdtempSync(path.join(path.dirname(local.data), '.tailor-restore-'));
    const pairs = [
        [path.join(local.data, 'shop.sqlite'), 'shop.sqlite'], [path.join(local.data, 'app-key'), 'app-key'],
        [path.join(local.project, 'public/storage'), 'public-storage'], [path.join(local.data, 'storage/app/public'), 'storage-public'],
    ];
    const swapped = [];
    try {
        copyTree(folder, path.join(staging, 'incoming'));
        verifyBackup(path.join(staging, 'incoming'), version);
        // Keep any SQLite sidecars for rollback; restored snapshots are self-contained.
        for (const suffix of ['-wal', '-shm', '-journal']) {
            const live = path.join(local.data, 'shop.sqlite') + suffix, previous = path.join(staging, 'sidecar' + suffix);
            if (fs.existsSync(live)) { fs.renameSync(live, previous); swapped.push([live, previous]); }
        }
        // Caller stops the backend before restoration, so no live writes are lost.
        for (const [live, relative] of pairs) {
            const previous = path.join(staging, 'previous', relative);
            fs.mkdirSync(path.dirname(previous), { recursive: true });
            if (fs.existsSync(live)) fs.renameSync(live, previous);
            swapped.push([live, previous]);
            const incoming = path.join(staging, 'incoming', relative);
            if (fs.existsSync(incoming)) copyTree(incoming, live);
            else fs.mkdirSync(live, { recursive: true });
        }
        for (const suffix of ['-wal', '-shm', '-journal']) fs.rmSync(path.join(local.data, 'shop.sqlite') + suffix, { force: true });
        local.env.APP_KEY = fs.readFileSync(path.join(local.data, 'app-key'), 'utf8').trim();
        await migrate();
        // Old sessions must not carry login state into the restored database.
        const sessions = path.join(local.data, 'storage/framework/sessions');
        fs.rmSync(sessions, { recursive: true, force: true }); fs.mkdirSync(sessions, { recursive: true });
    } catch (error) {
        if (swapped.some(([live]) => live === path.join(local.data, 'shop.sqlite'))) for (const suffix of ['-wal', '-shm', '-journal']) fs.rmSync(path.join(local.data, 'shop.sqlite') + suffix, { force: true });
        for (const [live, previous] of swapped.reverse()) {
            fs.rmSync(live, { recursive: true, force: true });
            if (fs.existsSync(previous)) fs.renameSync(previous, live);
        }
        local.env.APP_KEY = fs.readFileSync(path.join(local.data, 'app-key'), 'utf8').trim();
        throw error;
    } finally { fs.rmSync(staging, { recursive: true, force: true }); }
}
module.exports = { createBackup, verifyBackup, restoreBackup, day };
