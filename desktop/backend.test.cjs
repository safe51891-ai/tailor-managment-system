'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { prepareLocalData, initialize } = require('./local-data.cjs');
const { phpBinary, freePort, allowedNavigation } = require('./backend.cjs');
test('explicit PHP path is respected, including spaces', () => assert.equal(phpBinary({TAILOR_PHP_BIN:'C:\\Program Files\\PHP\\php.exe'}, 'win32'), 'C:\\Program Files\\PHP\\php.exe'));
test('desktop permits only its local backend origin', () => {
    const origin = 'http://127.0.0.1:12345';
    assert(allowedNavigation(origin + '/orders', origin));
    for (const url of ['https://example.com','http://127.0.0.1:12346','file:///etc/passwd','javascript:alert(1)','bad-url']) assert(!allowedNavigation(url, origin));
});
test('local backend port allocation works', async () => assert((await freePort()) > 0));
test('updating application files preserves local database, uploads and encryption key', () => {
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tailor-data-test-'));
    try {
        const resources = path.join(temp, 'resources'), userData = path.join(temp, 'user');
        fs.mkdirSync(path.join(resources, 'shop'), { recursive: true });
        fs.writeFileSync(path.join(resources, 'shop/release.json'), '{"version":1}');
        const first = prepareLocalData(resources, userData);
        fs.writeFileSync(first.env.DB_DATABASE, 'customer data');
        fs.writeFileSync(path.join(first.project, 'public/storage/avatars/avatar.png'), 'upload');
        fs.writeFileSync(path.join(resources, 'shop/release.json'), '{"version":2}');
        const next = prepareLocalData(resources, userData);
        assert.equal(fs.readFileSync(next.env.DB_DATABASE, 'utf8'), 'customer data');
        assert.equal(fs.readFileSync(path.join(next.project, 'public/storage/avatars/avatar.png'), 'utf8'), 'upload');
        assert.equal(next.env.APP_KEY, first.env.APP_KEY);
        assert.equal(next.env.DB_CONNECTION, 'sqlite');
    } finally { fs.rmSync(temp, { recursive: true, force: true }); }
});
test('fresh local installation migrates, validates owner account and makes daily SQLite backup', { skip: !fs.existsSync(path.resolve(__dirname, '../.local/desktop-stage/shop/vendor/autoload.php')) }, async () => {
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tailor-install-test-'));
    try {
        const local = prepareLocalData(path.resolve(__dirname, '../.local/desktop-stage'), temp);
        if (process.platform !== 'win32') local.env.TAILOR_PHP_BIN = phpBinary();
        local.env.TAILOR_DEFAULT_ACCOUNT = '0';
        assert.equal((await initialize(local.project, local.env)).needsSetup, true);
        await assert.rejects(initialize(local.project, local.env, { name: '123', username: 'owner', password: 'bad' }));
        assert.equal((await initialize(local.project, local.env, { name: 'Gul Khan', username: 'admin', password: 'admin@12', password_confirmation: 'admin@12' })).needsSetup, false);
        await assert.rejects(initialize(local.project, local.env, { name: 'Second Owner' }), /already exists/);
        assert.equal((await initialize(local.project, local.env)).needsSetup, false);
        const backups = fs.readdirSync(path.join(local.data, 'backups'));
        assert.equal(backups.length, 1);
        assert(fs.statSync(path.join(local.data, 'backups', backups[0])).size > 0);
    } finally { fs.rmSync(temp, { recursive: true, force: true }); }
});
test('startup resumes an interrupted users migration and preserves existing rows', { skip: !fs.existsSync(path.resolve(__dirname, '../.local/desktop-stage/shop/vendor/autoload.php')) }, async () => {
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tailor-migration-retry-'));
    try {
        const local = prepareLocalData(path.resolve(__dirname, '../.local/desktop-stage'), temp);
        if (process.platform !== 'win32') local.env.TAILOR_PHP_BIN = phpBinary();
        const php = local.env.TAILOR_PHP_BIN;
        const options = { cwd: local.project, env: local.env, encoding: 'utf8', timeout: 60000 };
        execFileSync(php, ['artisan', 'migrate', '--path=database/migrations/2014_10_12_000000_create_users_table.php', '--force', '--no-interaction'], options);
        execFileSync(php, ['-r', '$db=new PDO("sqlite:".getenv("DB_DATABASE")); $db->exec("INSERT INTO users (name,email,password) VALUES (\'Existing Owner\',\'owner@example.test\',\'preserved-hash\')");'], options);
        assert.equal((await initialize(local.project, local.env)).needsSetup, false);
        const result = JSON.parse(execFileSync(php, ['-r', '$db=new PDO("sqlite:".getenv("DB_DATABASE")); echo json_encode(["user"=>$db->query("SELECT name,email,password,username FROM users")->fetch(PDO::FETCH_ASSOC),"columns"=>$db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC)]);'], options));
        assert.deepEqual(result.user, { name: 'Existing Owner', email: 'owner@example.test', password: 'preserved-hash', username: '' });
        const username = result.columns.find(column => column.name === 'username');
        assert.equal(Number(username.notnull), 1);
        assert.equal(username.dflt_value, "''");
        assert(result.columns.some(column => column.name === 'avatar'));
        assert.equal((await initialize(local.project, local.env)).needsSetup, false);
        assert(fs.readdirSync(path.join(local.data, 'backups')).some(name => name.endsWith('.sqlite')));
    } finally { fs.rmSync(temp, { recursive: true, force: true }); }
});
test('shared installer creates the requested login once and preserves later password changes', async () => {
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tailor-default-login-'));
    try {
        const local = prepareLocalData(path.resolve(__dirname, '../.local/desktop-stage'), temp);
        local.env.TAILOR_PHP_BIN = phpBinary();
        assert.equal((await initialize(local.project, local.env)).needsSetup, false);
        const probe = `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $u=App\\Models\\User::where('username','admin')->firstOrFail(); if(!Illuminate\\Support\\Facades\\Hash::check('admin@12',$u->password)) exit(2); $u->password=Illuminate\\Support\\Facades\\Hash::make('Changed-password-42'); $u->save();`;
        const options = { cwd: local.project, env: local.env, encoding: 'utf8' };
        execFileSync(local.env.TAILOR_PHP_BIN, ['-r', probe], options);
        await initialize(local.project, local.env);
        execFileSync(local.env.TAILOR_PHP_BIN, ['-r', `require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $u=App\\Models\\User::where('username','admin')->firstOrFail(); if(!Illuminate\\Support\\Facades\\Hash::check('Changed-password-42',$u->password)) exit(3);`], options);
    } finally { fs.rmSync(temp, { recursive: true, force: true }); }
});
