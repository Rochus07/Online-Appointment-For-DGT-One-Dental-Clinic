/**
 * Reveal-password toggle. Wires any `.pw-toggle` button that sits next to a
 * password `<input>` inside a `.pw-field` wrapper — see login.php,
 * register.php, reset_password.php and modules/shared/profile.php for the
 * markup. Delegated on `document` so it needs no re-wiring after nav.js
 * swaps `#app-content`, and it is safe to load once globally (layout.php)
 * or once per standalone auth page.
 */
(function () {
    'use strict';
    if (window.__pwToggleWired) return;
    window.__pwToggleWired = true;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.pw-toggle');
        if (!btn) return;
        var wrap = btn.closest('.pw-field');
        var input = wrap && wrap.querySelector('input');
        if (!input) return;

        var reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        var eye = btn.querySelector('.pw-eye');
        var eyeOff = btn.querySelector('.pw-eye-off');
        // The icon shows the current state: slashed eye = hidden, open eye = visible.
        if (eye) eye.classList.toggle('hidden', !reveal);
        if (eyeOff) eyeOff.classList.toggle('hidden', reveal);
    });
})();
