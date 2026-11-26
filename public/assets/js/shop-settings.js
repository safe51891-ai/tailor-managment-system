(() => {
    const preview = document.getElementById('brand-preview');
    if (!preview) return;
    const fields = {primary_color: 'primary', sidebar_color: 'sidebar', background_color: 'background'};
    function textColor(hex) {
        const c = hex.slice(1).match(/../g).map(v => {
            const n = parseInt(v, 16) / 255;
            return n <= 0.04045 ? n / 12.92 : Math.pow((n + .055) / 1.055, 2.4);
        });
        const l = c[0] * .2126 + c[1] * .7152 + c[2] * .0722;
        return 1.05 / (l + .05) >= (l + .05) / .05 ? '#ffffff' : '#000000';
    }
    function colors() {
        Object.entries(fields).forEach(([field, variable]) => {
            const value = document.getElementById('shop-' + field).value;
            preview.style.setProperty('--preview-' + variable, value);
            if (variable !== 'background') preview.style.setProperty('--preview-' + variable + '-text', textColor(value));
            document.querySelector('[data-color-value="' + field + '"]').textContent = value;
        });
    }
    Object.keys(fields).forEach(field => document.getElementById('shop-' + field).addEventListener('input', colors));
    document.getElementById('shop-name').addEventListener('input', event => {
        document.getElementById('preview-name').textContent = event.target.value || 'Your shop';
    });
    document.getElementById('shop-default-colors').addEventListener('click', () => {
        Object.entries({primary_color: '#176458', sidebar_color: '#182e36', background_color: '#f5f3ed'}).forEach(([field, value]) => document.getElementById('shop-' + field).value = value);
        colors();
    });
    const logo = document.getElementById('preview-logo'), original = logo.getAttribute('src');
    let objectURL;
    function logoPreview() {
        if (objectURL) URL.revokeObjectURL(objectURL);
        const file = document.getElementById('shop-logo').files[0];
        if (file) {
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                document.getElementById('brand-preview-status').textContent = 'Choose a PNG, JPG or WebP image up to 2 MB.';
                return;
            }
            objectURL = URL.createObjectURL(file); logo.src = objectURL; logo.hidden = false;
        } else if (!document.getElementById('remove-logo')?.checked && original) {
            logo.src = original; logo.hidden = false;
        } else { logo.hidden = true; logo.removeAttribute('src'); }
        document.getElementById('brand-preview-status').textContent = 'Your changes are saved only when you select Save shop settings.';
    }
    document.getElementById('shop-logo').addEventListener('change', logoPreview);
    document.getElementById('remove-logo')?.addEventListener('change', logoPreview);
    colors();
    if (window.shopDesktop?.licenseStatus) {
        window.shopDesktop.licenseStatus().then(license => {
            if (!license || !license.activated) return;
            document.getElementById('shop-license-card').hidden = false;
            document.getElementById('shop-license-status').textContent = 'Activated for ' + license.customer + ' · This computer · No expiry';
            document.getElementById('shop-license-code').textContent = license.machineCode;
        }).catch(() => {});
    }
})();
