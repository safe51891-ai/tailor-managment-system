'use strict';
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');
const PRODUCT = 'tailor-shop-desktop';
const CODE_PATTERN = /^TS(?:-[A-F0-9]{8}){4}$/;
function machineCodeFromId(id) {
    if (typeof id !== 'string' || !id.trim()) throw new Error('The computer identity could not be read.');
    const digest = crypto.createHash('sha256').update(PRODUCT + '\0' + id.trim().toLowerCase()).digest('hex').slice(0, 32).toUpperCase();
    return 'TS-' + digest.match(/.{8}/g).join('-');
}
function getMachineCode(platform = process.platform, run = execFileSync) {
    const opts = { encoding: 'utf8', windowsHide: true, timeout: 10000 };
    let identity;
    if (platform === 'win32') {
        const output = run('reg.exe', ['QUERY', 'HKLM\\SOFTWARE\\Microsoft\\Cryptography', '/v', 'MachineGuid', '/reg:64'], opts);
        identity = output.match(/MachineGuid\s+REG_SZ\s+([a-fA-F0-9-]+)/)?.[1];
    } else if (platform === 'darwin') {
        const output = run('/usr/sbin/ioreg', ['-rd1', '-c', 'IOPlatformExpertDevice'], opts);
        identity = output.match(/"IOPlatformUUID"\s*=\s*"([a-fA-F0-9-]+)"/)?.[1];
    } else if (platform === 'linux') {
        identity = fs.readFileSync('/etc/machine-id', 'utf8');
    } else throw new Error('This computer does not support offline activation.');
    if (!identity) throw new Error('The computer identity could not be read. Contact the app provider.');
    return machineCodeFromId(identity);
}
function verifyLicense(contents, publicKey, machineCode) {
    try {
        if (!CODE_PATTERN.test(machineCode)) throw new Error('Invalid computer code.');
        if (Buffer.byteLength(contents) > 16384) throw new Error('License file is too large.');
        const envelope = JSON.parse(contents);
        if (envelope.format !== 'tailor-shop-license' || typeof envelope.payload !== 'string' || typeof envelope.signature !== 'string') throw new Error('Invalid license format.');
        if (!/^[A-Za-z0-9+/]+={0,2}$/.test(envelope.payload) || !/^[A-Za-z0-9+/]+={0,2}$/.test(envelope.signature)) throw new Error('Invalid license encoding.');
        const bytes = Buffer.from(envelope.payload, 'base64');
        const signature = Buffer.from(envelope.signature, 'base64');
        if (signature.length !== 64 || !crypto.verify(null, bytes, publicKey, signature)) throw new Error('The license signature is invalid.');
        const payload = JSON.parse(bytes.toString('utf8'));
        if (payload.version !== 1 || payload.product !== PRODUCT || payload.machineCode !== machineCode) throw new Error('This license is for a different computer or app.');
        if (typeof payload.customer !== 'string' || !payload.customer.trim() || payload.customer.length > 160 || typeof payload.licenseId !== 'string' || !/^[a-f0-9-]{36}$/.test(payload.licenseId) || !Number.isFinite(Date.parse(payload.issuedAt)) || payload.type !== 'permanent') throw new Error('The license details are invalid.');
        return payload;
    } catch (error) {
        if (error instanceof SyntaxError) throw new Error('Choose a valid Tailor Shop .lic file from your app provider.');
        throw error;
    }
}
function licenseStatus(userData, publicKey, machineCode) {
    const file = path.join(userData, 'activation.lic');
    if (!fs.existsSync(file)) return { activated: false, machineCode };
    try { return { activated: true, machineCode, license: verifyLicense(fs.readFileSync(file, 'utf8'), publicKey, machineCode) }; }
    catch (error) { return { activated: false, machineCode, error: error.message }; }
}
function importLicense(source, userData, publicKey, machineCode) {
    if (fs.statSync(source).size > 16384) throw new Error('Choose the small .lic file supplied by your app provider.');
    const contents = fs.readFileSync(source, 'utf8');
    const payload = verifyLicense(contents, publicKey, machineCode);
    fs.mkdirSync(userData, { recursive: true });
    const temp = path.join(userData, 'activation-' + crypto.randomBytes(8).toString('hex') + '.tmp');
    try {
        fs.writeFileSync(temp, contents, { flag: 'wx', mode: 0o600 });
        fs.renameSync(temp, path.join(userData, 'activation.lic'));
    } finally { if (fs.existsSync(temp)) fs.unlinkSync(temp); }
    return payload;
}
module.exports = { PRODUCT, CODE_PATTERN, machineCodeFromId, getMachineCode, verifyLicense, licenseStatus, importLicense };
