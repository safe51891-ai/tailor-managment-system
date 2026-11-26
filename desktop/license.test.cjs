'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { PRODUCT, getMachineCode, machineCodeFromId, verifyLicense, importLicense, licenseStatus } = require('./license.cjs');
const { publicKey, privateKey } = crypto.generateKeyPairSync('ed25519');
const machineCode = machineCodeFromId('11111111-2222-3333-4444-555555555555');
function signed(overrides = {}, key = privateKey) {
    const payload = { version: 1, product: PRODUCT, type: 'permanent', licenseId: crypto.randomUUID(), customer: 'Khyber Tailors', machineCode, issuedAt: new Date().toISOString(), ...overrides };
    const bytes = Buffer.from(JSON.stringify(payload));
    return JSON.stringify({ format: 'tailor-shop-license', payload: bytes.toString('base64'), signature: crypto.sign(null, bytes, key).toString('base64') });
}
test('machine code uses a stable Windows identity and does not expose the raw ID', () => {
    let observed;
    const code = getMachineCode('win32', (command, args) => {
        observed = [command, args];
        return '\nMachineGuid    REG_SZ    11111111-2222-3333-4444-555555555555\n';
    });
    assert.equal(code, machineCode);
    assert.equal(machineCodeFromId('  ABCD '), machineCodeFromId('abcd'));
    assert(!code.includes('11111111'));
    assert.equal(observed[0], 'reg.exe');
    assert(observed[1].includes('/reg:64'));
    assert.throws(() => getMachineCode('win32', () => ''), /identity/);
});
test('signed permanent license verifies offline for its computer only', () => {
    const license = signed();
    assert.equal(verifyLicense(license, publicKey, machineCode).customer, 'Khyber Tailors');
    assert.throws(() => verifyLicense(license, publicKey, machineCodeFromId('other-computer')), /different computer/);
    assert.throws(() => verifyLicense(signed({ product: 'other-app' }), publicKey, machineCode), /different computer or app/);
    assert.throws(() => verifyLicense(signed({ type: 'trial' }), publicKey, machineCode), /details/);
});
test('forged, edited, malformed and oversized licenses are rejected', () => {
    const envelope = JSON.parse(signed());
    const data = JSON.parse(Buffer.from(envelope.payload, 'base64'));
    data.customer = 'Tampered';
    envelope.payload = Buffer.from(JSON.stringify(data)).toString('base64');
    assert.throws(() => verifyLicense(JSON.stringify(envelope), publicKey, machineCode), /signature/);
    assert.throws(() => verifyLicense(signed({}, crypto.generateKeyPairSync('ed25519').privateKey), publicKey, machineCode), /signature/);
    for (const value of ['{}', 'not-json', '{"format":"tailor-shop-license","payload":"@@","signature":"@@"}', 'x'.repeat(17000)]) {
        assert.throws(() => verifyLicense(value, publicKey, machineCode));
    }
});
test('activation persists on reopening, rejects copied activations, and failed imports preserve a valid license', () => {
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tailor-license-'));
    try {
        const good = path.join(temp, 'good.lic'), bad = path.join(temp, 'bad.lic'), user = path.join(temp, 'user');
        fs.writeFileSync(good, signed()); fs.writeFileSync(bad, signed({ machineCode: machineCodeFromId('other') }));
        assert.equal(licenseStatus(user, publicKey, machineCode).activated, false);
        importLicense(good, user, publicKey, machineCode);
        assert.equal(licenseStatus(user, publicKey, machineCode).activated, true);
        assert.equal(licenseStatus(user, publicKey, machineCodeFromId('other')).activated, false);
        assert.throws(() => importLicense(bad, user, publicKey, machineCode), /different computer/);
        assert.equal(licenseStatus(user, publicKey, machineCode).activated, true);
        assert.deepEqual(fs.readdirSync(user), ['activation.lic']);
    } finally { fs.rmSync(temp, { recursive: true, force: true }); }
});
