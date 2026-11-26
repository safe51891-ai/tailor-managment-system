'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { spawn } = require('node:child_process');
const { phpArguments } = require('./backend.cjs');

function prepareLocalData(resources, userData) {
    const source = path.join(resources, 'shop');
    const fingerprint = fs.readFileSync(path.join(source, 'release.json'), 'utf8');
    const project = path.join(userData, 'application');
    const data = path.join(userData, 'data');
    fs.mkdirSync(data, { recursive: true, mode: 0o700 });
    const installed = path.join(project, 'release.json');
    if (!fs.existsSync(installed) || fs.readFileSync(installed, 'utf8') !== fingerprint) {
        fs.mkdirSync(project, { recursive: true });
        // No customer files, environment or database are present in the release.
        fs.cpSync(source, project, { recursive: true });
    }
    const storage = path.join(data, 'storage');
    for (const dir of ['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']) fs.mkdirSync(path.join(storage, dir), { recursive: true });
    // Public uploads stay in this writable application folder across upgrades.
    fs.mkdirSync(path.join(project, 'public/storage/avatars'), { recursive: true });
    fs.mkdirSync(path.join(project, 'public/storage/parts'), { recursive: true });
    const keyFile = path.join(data, 'app-key');
    if (!fs.existsSync(keyFile)) fs.writeFileSync(keyFile, 'base64:' + crypto.randomBytes(32).toString('base64'), { mode: 0o600, flag: 'wx' });
    const database = path.join(data, 'shop.sqlite');
    if (!fs.existsSync(database)) fs.writeFileSync(database, '', { mode: 0o600, flag: 'wx' });
    fs.writeFileSync(path.join(project, '.env'), 'APP_NAME="Tailor Shop"\nAPP_ENV=production\nAPP_DEBUG=false\n', { mode: 0o600 });
    const env = { ...process.env, APP_ENV: 'production', APP_KEY: fs.readFileSync(keyFile, 'utf8').trim(), APP_DEBUG: 'false', DEBUGBAR_ENABLED: 'false',
        TAILOR_DEFAULT_ACCOUNT: '1', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_FOREIGN_KEYS: 'true', CACHE_DRIVER: 'file', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync',
        TAILOR_STORAGE_PATH: storage, TAILOR_PHP_BIN: path.join(resources, 'php', 'php.exe'), TAILOR_INITIALIZE_SCRIPT: path.join(resources, 'initialize.php') };
    return { project, env, data };
}
function initialize(project, env, account) {
    return new Promise((resolve, reject) => {
        const script = env.TAILOR_INITIALIZE_SCRIPT || path.join(__dirname, 'initialize.php');
        const child = spawn(env.TAILOR_PHP_BIN, [...phpArguments(env), script, account ? 'account' : 'migrate'], { cwd: project, env, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
        let output = '', error = '';
        child.stdout.on('data', chunk => { output += chunk; });
        child.stderr.on('data', chunk => { error += chunk; });
        child.once('error', reject);
        child.once('close', code => {
            if (code !== 0) return reject(new Error(error || output.trim() || 'Could not prepare the local database.'));
            try { resolve(JSON.parse(output)); } catch { reject(new Error('Unexpected database setup response.')); }
        });
        child.stdin.end(account ? JSON.stringify(account) : '');
    });
}
module.exports = { prepareLocalData, initialize };
