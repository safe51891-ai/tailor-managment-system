'use strict';
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const http = require('node:http');
const { spawn } = require('node:child_process');

function phpBinary(env = process.env, platform = process.platform) {
    if (env.TAILOR_PHP_BIN) return env.TAILOR_PHP_BIN;
    const candidates = platform === 'win32' ? [] : ['/opt/homebrew/opt/php@7.4/bin/php', '/usr/local/opt/php@7.4/bin/php'];
    return candidates.find(file => fs.existsSync(file)) || (platform === 'win32' ? 'php.exe' : 'php');
}
function freePort() {
    return new Promise((resolve, reject) => {
        const server = net.createServer();
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => {
            const port = server.address().port;
            server.close(error => error ? reject(error) : resolve(port));
        });
    });
}
function phpArguments(env = process.env) {
    if (!env.TAILOR_INITIALIZE_SCRIPT || path.basename(phpBinary(env)) !== 'php.exe') return [];
    const directory = path.dirname(phpBinary(env));
    return ['-c', path.join(directory, 'php.ini'), '-d', 'extension_dir=' + path.join(directory, 'ext')];
}
function reachable(url) {
    return new Promise(resolve => {
        const req = http.get(url + '/login', res => { res.resume(); resolve(res.statusCode === 200); });
        req.setTimeout(1000, () => req.destroy());
        req.on('error', () => resolve(false));
    });
}
async function startBackend(project, log, env = process.env) {
    if (!fs.existsSync(path.join(project, 'vendor', 'autoload.php')) || !fs.existsSync(path.join(project, '.env'))) {
        throw new Error('The local Tailor Shop project needs its dependencies and database configuration before starting.');
    }
    const port = await freePort(), origin = `http://127.0.0.1:${port}`;
    const child = spawn(phpBinary(env), [...phpArguments(env), '-S', `127.0.0.1:${port}`, '-t', 'public', 'server.php'], {
        cwd: project, windowsHide: true,
        env: { ...env, APP_URL: origin, ASSET_URL: origin, APP_DEBUG: 'false', DEBUGBAR_ENABLED: 'false' },
        stdio: ['ignore', 'pipe', 'pipe']
    });
    let failure = null;
    child.once('error', () => { failure = new Error('PHP could not start. Install the project’s compatible PHP runtime or set TAILOR_PHP_BIN to its executable.'); });
    child.once('exit', code => { failure = new Error(`The local backend stopped (code ${code}). Check the desktop log and database service.`); });
    child.stdout.on('data', data => { if (!log.writableEnded) log.write(data); });
    child.stderr.on('data', data => { if (!log.writableEnded) log.write(data); });
    const stop = () => { if (child.exitCode === null && !child.killed) child.kill(); };
    for (let attempt = 0; attempt < 80; attempt++) {
        if (failure) { stop(); throw failure; }
        if (await reachable(origin)) return { origin, child, stop };
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    stop();
    throw new Error('The local app did not become ready. Check that the shop database service is running.');
}
function allowedNavigation(url, origin) {
    try { return new URL(url).origin === origin; } catch { return false; }
}
module.exports = { phpBinary, phpArguments, freePort, startBackend, allowedNavigation };
