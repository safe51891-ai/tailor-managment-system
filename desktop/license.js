'use strict';
const status = document.getElementById('status');
function message(text, error = false) { status.textContent = text; status.classList.toggle('error', error); }
window.shopLicense.info().then(info => {
    document.getElementById('machine-code').value = info.machineCode;
    if (info.error) message(info.error + ' Request a license for the code above.', true);
}).catch(() => message('Unable to read the computer code. Close the app and contact your provider.', true));
document.getElementById('copy-code').addEventListener('click', async () => {
    try { if (await window.shopLicense.copy()) message('Computer code copied. Send it to your app provider.'); }
    catch { message('Select and copy the computer code above.', true); }
});
async function action(button, method, success) {
    const buttons = document.querySelectorAll('button');
    buttons.forEach(b => { b.disabled = true; });
    try { const result = await method(); if (result.ok) message(success); else if (result.error) message(result.error, true); }
    catch { message('The operation failed. Please try again.', true); }
    finally { buttons.forEach(b => { b.disabled = false; }); }
}
document.getElementById('save-request').addEventListener('click', event => action(event.target, () => window.shopLicense.saveRequest(), 'Computer code saved. Send that file to your app provider.'));
document.getElementById('activate').addEventListener('click', event => action(event.target, () => window.shopLicense.import(), 'Activated. Opening your shop…'));
