'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { phpBinary } = require('./backend.cjs');
const { createBackup, verifyBackup, restoreBackup } = require('./backup.cjs');
const { backupUI } = require('./backup-ui.cjs');
const script = path.join(__dirname, 'backup.php');
function fixture() {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'tailor-backup-test-'));
    const local = { project: path.join(root, 'application'), data: path.join(root, 'data'), env: { ...process.env, TAILOR_PHP_BIN: phpBinary() } };
    for (const directory of [local.data, path.join(local.project, 'public/storage'), path.join(local.data, 'storage/app/public')]) fs.mkdirSync(directory, { recursive: true });
    fs.writeFileSync(path.join(local.data, 'app-key'), 'base64:' + crypto.randomBytes(32).toString('base64'));
    local.env.APP_KEY = fs.readFileSync(path.join(local.data, 'app-key'), 'utf8');
    fs.writeFileSync(path.join(local.project, 'public/storage/logo.png'), 'saved shop logo');
    fs.writeFileSync(path.join(local.data, 'storage/app/public/photo.png'), 'saved customer photo');
    execFileSync(phpBinary(), ['-r', '$db=new PDO("sqlite:".$argv[1]); $db->exec("CREATE TABLE orders (name TEXT); INSERT INTO orders VALUES (\'Saved suit\')");', path.join(local.data, 'shop.sqlite')]);
    return { root, local, destination: path.join(root, 'external-drive') };
}
test('full backup includes a checked SQLite snapshot, encryption key and uploads; restore rolls back on failure', async () => {
    const { root, local, destination } = fixture();
    try {
        const saved = await createBackup(local, destination, script, '1.0.2');
        const manifest = verifyBackup(saved.folder, '1.0.2');
        assert.equal(manifest.files.length, 4);
        fs.writeFileSync(path.join(local.project, 'public/storage/logo.png'), 'new logo');
        fs.writeFileSync(path.join(local.data, 'app-key'), 'base64:' + crypto.randomBytes(32).toString('base64'));
        const currentKey = fs.readFileSync(path.join(local.data, 'app-key'), 'utf8');
        await assert.rejects(restoreBackup(local, saved.folder, '1.0.2', async () => { throw new Error('Migration failed'); }), /Migration failed/);
        assert.equal(fs.readFileSync(path.join(local.project, 'public/storage/logo.png'), 'utf8'), 'new logo');
        assert.equal(fs.readFileSync(path.join(local.data, 'app-key'), 'utf8'), currentKey);
        await restoreBackup(local, saved.folder, '1.0.2', async () => {});
        assert.equal(fs.readFileSync(path.join(local.project, 'public/storage/logo.png'), 'utf8'), 'saved shop logo');
        assert.equal(local.env.APP_KEY, fs.readFileSync(path.join(saved.folder, 'app-key'), 'utf8'));
        const result = execFileSync(phpBinary(), ['-r', '$db=new PDO("sqlite:".$argv[1]); echo $db->query("SELECT name FROM orders")->fetchColumn();', path.join(local.data, 'shop.sqlite')], { encoding: 'utf8' });
        assert.equal(result, 'Saved suit');
        fs.appendFileSync(path.join(saved.folder, 'shop.sqlite'), 'tampered');
        assert.throws(() => verifyBackup(saved.folder, '1.0.2'), /damaged/);
    } finally { fs.rmSync(root, { recursive: true, force: true }); }
});
test('unsafe destinations, newer releases and path traversal backups are rejected', async () => {
    const { root, local, destination } = fixture();
    try {
        await assert.rejects(createBackup(local, path.join(local.data, 'backups'), script, '1.0.2'), /outside/);
        const saved = await createBackup(local, destination, script, '1.0.2');
        assert.throws(() => verifyBackup(saved.folder, '1.0.1'), /newer/);
        const manifest = JSON.parse(fs.readFileSync(path.join(saved.folder, 'backup.json')));
        manifest.files[0].path = '../app-key';
        fs.writeFileSync(path.join(saved.folder, 'backup.json'), JSON.stringify(manifest));
        assert.throws(() => verifyBackup(saved.folder, '1.0.2'), /Invalid file/);
    } finally { fs.rmSync(root, { recursive: true, force: true }); }
});
test('configured backups run once per day, support manual snapshots, and report disconnected drives', async () => {
    const { root, local, destination } = fixture();
    try {
        fs.mkdirSync(destination);
        fs.writeFileSync(path.join(root, 'backup-settings.json'), JSON.stringify({ folder: destination, prompted: true }));
        const ui = backupUI({ local, userData: root, script, version: '1.0.2', dialog: {}, getWindow: () => null });
        assert(await ui.run()); assert.equal(await ui.run(), undefined);
        assert(await ui.run(true)); assert.equal(fs.readdirSync(destination).length, 2);
        fs.rmSync(destination, { recursive: true }); fs.writeFileSync(destination, 'unavailable drive');
        await assert.rejects(ui.run(true)); assert(ui.status().error);
        assert(ui.status().lastSuccess);
    } finally { fs.rmSync(root, { recursive: true, force: true }); }
});
test('unchecking automatic backup persists, pauses daily and closing backups, and preserves manual backup', async () => {
    const { root, local, destination } = fixture();
    try {
        fs.mkdirSync(destination);
        fs.writeFileSync(path.join(root, 'backup-settings.json'), JSON.stringify({ folder: destination, prompted: true }));
        const options = { local, userData: root, script, version: '1.0.2', dialog: {}, getWindow: () => null };
        const ui = backupUI(options);
        assert.equal(ui.status().automatic, true);
        await ui.setAutomatic(false);
        const reopened = backupUI(options);
        assert.equal(reopened.status().automatic, false);
        assert.equal(await reopened.run(), undefined);
        assert.equal(await reopened.runOnClose(), undefined);
        assert.equal(fs.readdirSync(destination).length, 0);
        assert(await reopened.run(true));
        assert.equal(reopened.status().automatic, false);
        await reopened.setAutomatic(true);
        assert.equal(reopened.status().automatic, true);
        assert(await reopened.runOnClose());
        assert.equal(fs.readdirSync(destination).length, 2);
        assert.equal(backupUI(options).status().automatic, true);
        await assert.rejects(reopened.setAutomatic('false'), /Choose whether/);
    } finally { fs.rmSync(root, { recursive: true, force: true }); }
});
test('enabling automatic backup requires a folder; cancel leaves it unchecked', async () => {
    const { root, local, destination } = fixture();
    try {
        let canceled = true;
        const ui = backupUI({ local, userData: root, script, version: '1.0.2', getWindow: () => null,
            dialog: { showOpenDialog: async () => ({ canceled, filePaths: canceled ? [] : [destination] }) } });
        assert.equal(ui.status().automatic, false);
        assert.equal((await ui.setAutomatic(true)).automatic, false);
        assert.equal(ui.status().folder, '');
        canceled = false;
        assert.equal((await ui.setAutomatic(true)).automatic, true);
        assert.equal(ui.status().folder, destination);
        assert.equal(fs.readdirSync(destination).length, 1);
    } finally { fs.rmSync(root, { recursive: true, force: true }); }
});
