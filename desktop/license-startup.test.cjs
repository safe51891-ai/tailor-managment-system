'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
async function blockedStartup(code) {
    const calls = [];
    let startup;
    const app = {
        isPackaged: true, requestSingleInstanceLock: () => true, on: () => {},
        whenReady: () => ({ then: callback => { startup = Promise.resolve().then(callback); return startup; } }),
        getPath: () => '/test-user-data', getVersion: () => '1.0.6', quit: () => calls.push('quit')
    };
    const error = Object.assign(new Error('License not accepted'), { code });
    const mocks = {
        electron: { app, dialog: { showErrorBox: () => calls.push('error-dialog') } },
        'node:path': path,
        'node:fs': { createWriteStream: () => ({ write: () => {} }) },
        './backend.cjs': { startBackend: () => calls.push('start-backend') },
        './local-data.cjs': { prepareLocalData: () => calls.push('touch-shop-data') },
        './backup-ui.cjs': {}, './backup.cjs': {},
        './license-ui.cjs': { ensureActivated: async () => { calls.push('activation'); throw error; } }
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, 'main.cjs'), 'utf8'), {
        require: name => { if (!(name in mocks)) throw Error('Unexpected require: ' + name); return mocks[name]; },
        process: { env: {}, platform: 'win32' }, __dirname, setTimeout, setInterval, clearInterval
    });
    await startup;
    return calls;
}
test('packaged app cannot start backend or touch shop data before successful activation', async () => {
    assert.deepEqual(await blockedStartup('BAD_LICENSE'), ['activation', 'error-dialog', 'quit']);
});
test('closing activation exits quietly without starting or changing the shop', async () => {
    assert.deepEqual(await blockedStartup('ACTIVATION_CANCELLED'), ['activation', 'quit']);
});
