/**
 * Strong-password helper shared by every screen that sets a password (My
 * Profile, reset-password page, forgot-password modal). Mirrors the rule in
 * includes/functions.php isStrongPassword(): 8+ characters with an uppercase
 * letter, a lowercase letter, a number and a special character.
 *
 *   const sp = window.attachPwStrength(inputEl);   // adds the meter + hint
 *   sp.ok()                                        // true when the rule is met
 *
 * The meter is inserted right after the input's `.pw-field` wrapper (the same
 * 4-segment bar + Weak/Fair/Good/Strong label the Create Account form uses).
 */
(function () {
    'use strict';
    var MSG = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.';
    var HINT = 'Use 8+ characters with uppercase, lowercase, a number, and a symbol.';
    var METER = {
        1: { segs: 1, label: 'Weak',   bar: 'bg-danger',       text: 'text-danger' },
        2: { segs: 1, label: 'Weak',   bar: 'bg-danger',       text: 'text-danger' },
        3: { segs: 2, label: 'Fair',   bar: 'bg-warn',         text: 'text-warn' },
        4: { segs: 3, label: 'Good',   bar: 'bg-dgt-teal-500', text: 'text-dgt-teal-600' },
        5: { segs: 4, label: 'Strong', bar: 'bg-ok',           text: 'text-ok' }
    };

    function checks(pw) {
        return {
            len: pw.length >= 8,
            lower: /[a-z]/.test(pw),
            upper: /[A-Z]/.test(pw),
            digit: /[0-9]/.test(pw),
            special: /[^a-zA-Z0-9]/.test(pw)
        };
    }
    function allOk(c) { return c.len && c.lower && c.upper && c.digit && c.special; }

    window.isStrongPassword = function (pw) { return allOk(checks(String(pw))); };
    window.STRONG_PASSWORD_MSG = MSG;

    /**
     * Red "passwords do not match" warning for a confirm field: red border on
     * the confirm input plus a message, shown as soon as the confirm box has
     * text that differs from the password. Pass an existing message element,
     * or omit it to have one created under the confirm field.
     */
    window.wirePwMatch = function (pwInput, confirmInput, msgEl) {
        if (!msgEl) {
            var wrap = confirmInput.closest('.pw-field') || confirmInput;
            msgEl = document.createElement('p');
            msgEl.className = '-mt-1 hidden text-left text-[12.5px] text-danger';
            msgEl.setAttribute('aria-live', 'polite');
            msgEl.textContent = 'Passwords do not match.';
            wrap.parentNode.insertBefore(msgEl, wrap.nextSibling);
        }
        function update() {
            var bad = confirmInput.value !== '' && confirmInput.value !== pwInput.value;
            msgEl.classList.toggle('hidden', !bad);
            confirmInput.classList.toggle('is-invalid', bad);
            confirmInput.setAttribute('aria-invalid', bad ? 'true' : 'false');
        }
        pwInput.addEventListener('input', update);
        confirmInput.addEventListener('input', update);
        update();
    };

    window.attachPwStrength = function (input) {
        var wrap = input.closest('.pw-field') || input;
        var box = document.createElement('div');
        box.className = '-mt-1';
        box.innerHTML =
            '<div class="flex gap-1.5" aria-hidden="true">' +
            '<span class="pw-seg h-1 flex-1 rounded-full bg-steel-200 transition-colors"></span>'.repeat(4) +
            '</div>' +
            '<p class="mt-1.5 flex items-center justify-between gap-3 text-left text-[12.5px] text-steel-400" aria-live="polite">' +
            '<span class="pw-hint">' + HINT + '</span><span class="pw-label shrink-0 font-semibold"></span></p>';
        wrap.parentNode.insertBefore(box, wrap.nextSibling);
        var segs = box.querySelectorAll('.pw-seg');
        var hint = box.querySelector('.pw-hint');
        var label = box.querySelector('.pw-label');

        function render() {
            var pw = input.value, c = checks(pw);
            var score = (c.len ? 1 : 0) + (c.lower ? 1 : 0) + (c.upper ? 1 : 0) + (c.digit ? 1 : 0) + (c.special ? 1 : 0);
            var cfg = pw.length ? (METER[score] || METER[1]) : null;
            segs.forEach(function (seg, i) {
                seg.className = 'pw-seg h-1 flex-1 rounded-full transition-colors ' + ((cfg && i < cfg.segs) ? cfg.bar : 'bg-steel-200');
            });
            label.textContent = cfg ? cfg.label : '';
            label.className = 'pw-label shrink-0 font-semibold ' + (cfg ? cfg.text : '');
            if (!pw.length) { hint.textContent = HINT; return; }
            if (allOk(c)) { hint.textContent = '✓ Meets all requirements.'; return; }
            var m = [];
            if (!c.len) m.push('8+ characters');
            if (!c.upper) m.push('an uppercase letter');
            if (!c.lower) m.push('a lowercase letter');
            if (!c.digit) m.push('a number');
            if (!c.special) m.push('a special character');
            hint.textContent = 'Add: ' + m.join(', ') + '.';
        }
        input.addEventListener('input', render);
        render();
        return { ok: function () { return allOk(checks(input.value)); }, render: render };
    };
})();
