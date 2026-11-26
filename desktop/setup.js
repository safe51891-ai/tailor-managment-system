document.getElementById('setup').addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.target.querySelector('button'), error = document.getElementById('error');
    error.textContent = '';
    const account = Object.fromEntries(new FormData(event.target));
    if (account.password !== account.password_confirmation) { error.textContent = 'Both passwords must match.'; return; }
    button.disabled = true;
    try {
        const result = await window.shopSetup.save(account);
        if (!result.ok) { error.textContent = result.error; button.disabled = false; }
    } catch { error.textContent = 'Account setup failed. Please try again.'; button.disabled = false; }
});

document.getElementById('restore-backup').addEventListener('click', async () => {
    const buttons = document.querySelectorAll('button'), error = document.getElementById('error');
    buttons.forEach(button => { button.disabled = true; }); error.textContent = '';
    try { const result = await window.shopSetup.restore(); if (!result.ok) error.textContent = result.error; }
    catch { error.textContent = 'Restore failed. Your existing data was kept.'; }
    finally { buttons.forEach(button => { button.disabled = false; }); }
});
