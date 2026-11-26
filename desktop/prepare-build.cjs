'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '..'), stage = path.join(root, '.local/desktop-stage');
const runtime = path.join(root, '.local/desktop-runtime');
for (const file of ['php-win.zip', 'vc_redist.x64.exe']) if (!fs.existsSync(path.join(runtime, file))) throw new Error('Missing runtime download: ' + file + '. See desktop/README.md.');
if (!fs.existsSync(path.join(root, 'vendor/autoload.php'))) throw new Error('Install Composer dependencies before building.');
fs.rmSync(stage, { recursive: true, force: true });
fs.mkdirSync(stage, { recursive: true });
const shop = path.join(stage, 'shop');
const digest = crypto.createHash('sha256');
function copyTree(source, target) {
    fs.mkdirSync(target, { recursive: true });
    for (const entry of fs.readdirSync(source, { withFileTypes: true })) {
        if (entry.isSymbolicLink()) continue;
        if (entry.name === '.git' || entry.name === '.env' || entry.name.startsWith('.env.') || entry.name === 'storage' || entry.name === 'node_modules') continue;
        if (/\.(sqlite|sql|log)$/.test(entry.name)) continue;
        const from = path.join(source, entry.name), to = path.join(target, entry.name);
        if (entry.isDirectory()) copyTree(from, to);
        else { const bytes = fs.readFileSync(from); digest.update(path.relative(root, from)); digest.update(bytes); fs.copyFileSync(from, to); }
    }
}
for (const directory of ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor']) copyTree(path.join(root, directory), path.join(shop, directory));
for (const name of ['artisan', 'server.php', 'composer.json', 'composer.lock']) fs.copyFileSync(path.join(root, name), path.join(shop, name));
// A package must never carry machine-specific Laravel cache files.
for (const name of fs.readdirSync(path.join(shop, 'bootstrap/cache'))) if (name.endsWith('.php')) fs.unlinkSync(path.join(shop, 'bootstrap/cache', name));
fs.writeFileSync(path.join(shop, 'release.json'), JSON.stringify({ version: require('./package.json').version, digest: digest.digest('hex') }));
fs.copyFileSync(path.join(__dirname, 'initialize.php'), path.join(stage, 'initialize.php'));
const php = path.join(stage, 'php');
fs.mkdirSync(php, { recursive: true });
const sevenZip = require('7zip-bin').path7za;
if (process.platform !== 'win32') fs.chmodSync(sevenZip, 0o755);
execFileSync(sevenZip, ['x', '-y', '-o' + php, path.join(runtime, 'php-win.zip')], { stdio: 'ignore' });
fs.writeFileSync(path.join(php, 'php.ini'), `extension_dir="ext"\nextension=mbstring\nextension=openssl\nextension=fileinfo\nextension=pdo_sqlite\nextension=sqlite3\nextension=curl\nextension=gd2\ndate.timezone=Asia/Karachi\ndisplay_errors=Off\nlog_errors=On\nmemory_limit=256M\n`);
fs.copyFileSync(path.join(runtime, 'vc_redist.x64.exe'), path.join(stage, 'vc_redist.x64.exe'));
console.log('Prepared clean application files, PHP, SQLite extensions and Microsoft runtime.');
