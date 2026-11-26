(function () {
    document.querySelectorAll('[data-go-back]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            if (document.referrer && new URL(document.referrer).origin === window.location.origin && window.history.length > 1) {
                event.preventDefault();
                window.history.back();
            }
        });
    });
    document.querySelectorAll('[data-toggle-password]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var input = document.getElementById(toggle.getAttribute('data-toggle-password'));
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', String(show));
        });
    });
    var deleteModal = document.getElementById('deleteConfirmModal');
    if (deleteModal && window.jQuery) {
        var deleteForm = document.getElementById('delete-confirm-form');
        var deleteId = document.getElementById('delete_id');
        var deleteSubmit = document.getElementById('delete-confirm-submit');
        var deleteCancel = document.getElementById('delete-confirm-cancel');
        var deleteLabel = document.getElementById('delete-record-label');
        var deleteTrigger = null, deleting = false;
        var confirmText = deleteSubmit.textContent;
        window.jQuery(document).on('click', '.deletebtn', function (event) {
            event.preventDefault();
            var id = this.getAttribute('data-id');
            if (!/^[1-9][0-9]*$/.test(id || '') || deleting) return;
            deleteTrigger = this;
            deleteId.value = id;
            deleteLabel.textContent = this.getAttribute('data-label') || 'Record #' + id;
            deleteSubmit.disabled = false;
            window.jQuery(deleteModal).modal('show');
        });
        window.jQuery(deleteModal).on('shown.bs.modal', function () { deleteCancel.focus(); });
        window.jQuery(deleteModal).on('hidden.bs.modal', function () {
            deleteId.value = '';
            deleteLabel.textContent = '';
            deleteSubmit.disabled = true;
            if (!deleting) {
                deleteSubmit.textContent = confirmText;
                if (deleteTrigger && deleteTrigger.isConnected) deleteTrigger.focus();
            }
        });
        deleteForm.addEventListener('submit', function (event) {
            if (deleting || !/^[1-9][0-9]*$/.test(deleteId.value)) { event.preventDefault(); return; }
            deleting = true;
            deleteSubmit.disabled = true;
            deleteSubmit.textContent = 'Processing…';
        });
    }
    var button = document.getElementById('shop-menu-toggle');
    var sidebar = document.getElementById('shop-navigation');
    var backdrop = document.getElementById('shop-nav-backdrop');
    if (button && sidebar && backdrop) {
        function closeMenu() {
            document.body.classList.remove('shop-nav-open');
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Open navigation');
            backdrop.hidden = true;
        }
        button.addEventListener('click', function () {
            if (document.body.classList.contains('shop-nav-open')) { closeMenu(); return; }
            document.body.classList.add('shop-nav-open');
            button.setAttribute('aria-expanded', 'true');
            button.setAttribute('aria-label', 'Close navigation');
            backdrop.hidden = false;
            sidebar.querySelector('a').focus();
        });
        backdrop.addEventListener('click', function () { closeMenu(); button.focus(); });
        document.addEventListener('keydown', function (event) {
            if (!document.body.classList.contains('shop-nav-open')) return;
            if (event.key === 'Escape') { closeMenu(); button.focus(); }
            if (event.key === 'Tab') {
                var targets = Array.from(sidebar.querySelectorAll('a, summary')).filter(function (el) { return el.getClientRects().length; });
                targets.push(button);
                var current = targets.indexOf(document.activeElement);
                var next = event.shiftKey ? current - 1 : current + 1;
                event.preventDefault();
                targets[(next + targets.length) % targets.length].focus();
            }
        });
        window.addEventListener('resize', function () { if (window.innerWidth > 991) closeMenu(); });
    }
    document.querySelectorAll('[data-dismiss-feedback]').forEach(function (button) {
        button.addEventListener('click', function () { button.closest('.shop-feedback').remove(); });
    });
    document.querySelectorAll('.garment-measurement-panel').forEach(function (panel) {
        var count = panel.querySelector('.garment-field-count');
        panel.addEventListener('input', function () {
            if (count) count.textContent = Array.from(panel.querySelectorAll('input[type="number"]')).filter(function (input) { return input.value !== ''; }).length + ' sizes entered';
        });
    });
    var errorSummary = document.getElementById('form-errors');
    if (errorSummary) errorSummary.focus();
    document.querySelectorAll('.invalid-feedback').forEach(function (message, index) {
        var field = message.parentElement.querySelector('input, select, textarea');
        if (!field) return;
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        if (!message.id) message.id = 'field-error-' + index;
        field.setAttribute('aria-describedby', Array.from(new Set(((field.getAttribute('aria-describedby') || '') + ' ' + message.id).trim().split(/\s+/))).join(' '));
    });
})();

(function () {
    var controls = document.getElementById('desktop-backup-controls');
    if (!controls || !window.shopDesktop) return;
    var button = document.getElementById('desktop-backups');
    var checkbox = document.getElementById('desktop-auto-backup');
    var message = document.getElementById('desktop-backup-status');
    function render(status) {
        controls.hidden = false;
        checkbox.checked = Boolean(status.automatic);
        checkbox.disabled = !status.enabled;
        message.textContent = !status.enabled ? 'Available in client installation' : status.error ? 'Backup needs attention' : !status.folder ? 'Choose a backup folder' : status.automatic ? 'Daily backup on' : 'Automatic backup off';
        message.title = status.error || (status.folder ? 'Folder: ' + status.folder + (status.lastSuccess ? '\nLast backup: ' + status.lastSuccess : '') : 'Check Automatic backup to choose a folder.');
    }
    function refresh() { return window.shopDesktop.backupStatus().then(render); }
    refresh().catch(function () { controls.hidden = false; checkbox.disabled = true; message.textContent = 'Backup settings unavailable'; });
    checkbox.addEventListener('change', function () {
        var value = checkbox.checked;
        checkbox.disabled = true;
        message.textContent = 'Updating backup setting…';
        window.shopDesktop.setAutomaticBackup(value).then(function (result) {
            render(result.status);
            if (!result.ok) { message.textContent = 'Could not update backup'; message.title = result.error || result.status.error; }
        }).catch(function () { refresh(); });
    });
    button.addEventListener('click', function () { window.shopDesktop.openBackups().then(refresh).catch(refresh); });
    setInterval(function () { if (!checkbox.disabled) refresh().catch(function () {}); }, 30000);
})();
