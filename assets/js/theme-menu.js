/**
 * Appearance menu (Light / Dark / Auto) for every screen that has dark mode.
 * Any button with [data-theme-menu] opens a small "Appearance" popover; the
 * choice is stored in localStorage['dgt_theme'] as 'light' | 'dark' | 'system'.
 *   - 'system' (Auto) follows the OS colour scheme live.
 *   - Nothing stored: the app follows the OS (Auto); the public landing page
 *     (body.floria) stays light until the visitor picks something.
 * The switch plays the circle-wipe reveal from the clicked button (View
 * Transitions API; instant when unsupported or reduced motion is requested).
 * The pre-paint snippets in each page's <head> apply the stored mode before
 * first paint; this file keeps it in sync afterwards.
 */
(function () {
    'use strict';
    if (window.__themeMenuWired) return;
    window.__themeMenuWired = true;

    var KEY = 'dgt_theme';
    var root = document.documentElement;
    var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    var pop = null, popBtn = null;

    function stored() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
    function isLanding() { return !!(document.body && document.body.classList.contains('floria')); }
    function mode() {
        var s = stored();
        if (s === 'light' || s === 'dark' || s === 'system') return s;
        return isLanding() ? 'light' : 'system';
    }
    function wantsDark(m) { return m === 'dark' || (m === 'system' && !!(mq && mq.matches)); }
    function apply(m) {
        root.classList.toggle('dark', wantsDark(m));
        root.setAttribute('data-theme-mode', m);
        var btns = document.querySelectorAll('[data-theme-menu]');
        for (var i = 0; i < btns.length; i++) btns[i].setAttribute('aria-pressed', root.classList.contains('dark') ? 'true' : 'false');
        if (pop) refreshActive();
    }
    function save(m) { try { localStorage.setItem(KEY, m); } catch (e) {} }

    function setMode(m, origin) {
        var run = function () { save(m); apply(m); };
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var willChange = wantsDark(m) !== root.classList.contains('dark');
        if (!willChange || reduce || typeof document.startViewTransition !== 'function' || !origin) { run(); return; }
        var r = origin.getBoundingClientRect();
        var x = r.left + r.width / 2, y = r.top + r.height / 2;
        var maxR = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));
        var t = document.startViewTransition(run);
        t.ready.then(function () {
            root.animate(
                { clipPath: ['circle(0px at ' + x + 'px ' + y + 'px)', 'circle(' + maxR + 'px at ' + x + 'px ' + y + 'px)'] },
                { duration: 650, easing: 'ease-in-out', pseudoElement: '::view-transition-new(root)' }
            );
        }).catch(function () {});
    }

    var ICON = {
        sun: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>',
        moon: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>',
        auto: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>'
    };

    function injectStyle() {
        if (document.getElementById('themeMenuStyle')) return;
        var st = document.createElement('style');
        st.id = 'themeMenuStyle';
        st.textContent =
            '.tm-pop{position:fixed;z-index:300;width:292px;max-width:calc(100vw - 16px);padding:14px;border-radius:14px;' +
            'background:#fff;color:#102f51;border:1px solid #d6dee4;box-shadow:0 18px 45px rgba(15,28,46,.22);font:13px/1.2 system-ui,"Segoe UI",sans-serif}' +
            'html.dark .tm-pop{background:#161d24;color:#e9edf1;border-color:rgba(255,255,255,.14);box-shadow:0 18px 45px rgba(0,0,0,.55)}' +
            '.tm-title{display:flex;align-items:center;gap:8px;margin-bottom:12px;color:#647380;font-weight:600}' +
            'html.dark .tm-title{color:#8a98a4}' +
            '.tm-row{display:flex;gap:8px}' +
            '.tm-opt{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 6px;border-radius:9px;cursor:pointer;' +
            'background:transparent;color:inherit;border:1px solid transparent;font:inherit;font-weight:600;transition:background-color .15s,border-color .15s}' +
            '.tm-opt:hover{background:rgba(100,115,128,.12)}' +
            '.tm-opt[aria-checked="true"]{border-color:#b4c1cb;background:rgba(100,115,128,.10)}' +
            'html.dark .tm-opt[aria-checked="true"]{border-color:rgba(255,255,255,.28);background:rgba(255,255,255,.08)}' +
            '.tm-opt:focus-visible{outline:2px solid #006cc0;outline-offset:1px}' +
            '@media (prefers-reduced-motion:reduce){.tm-opt{transition:none}}';
        document.head.appendChild(st);
    }

    function refreshActive() {
        var m = mode();
        var opts = pop.querySelectorAll('.tm-opt');
        for (var i = 0; i < opts.length; i++) opts[i].setAttribute('aria-checked', opts[i].dataset.mode === m ? 'true' : 'false');
    }

    function position() {
        var r = popBtn.getBoundingClientRect();
        var w = pop.offsetWidth;
        var left = Math.min(Math.max(8, r.right - w), window.innerWidth - w - 8);
        pop.style.left = left + 'px';
        pop.style.top = (r.bottom + 10) + 'px';
    }

    function teardown(refocus) {
        if (!pop) return;
        pop.remove(); pop = null;
        if (popBtn) { popBtn.setAttribute('aria-expanded', 'false'); if (refocus) popBtn.focus(); }
        popBtn = null;
        document.removeEventListener('keydown', onKey, true);
        window.removeEventListener('resize', onResize);
    }
    function onResize() { if (pop && popBtn) position(); }
    function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); teardown(true); } }

    function open(btn) {
        injectStyle();
        popBtn = btn;
        btn.setAttribute('aria-expanded', 'true');
        pop = document.createElement('div');
        pop.className = 'tm-pop';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', 'Appearance');
        pop.innerHTML =
            '<div class="tm-title">' + ICON.sun + '<span>Appearance</span></div>' +
            '<div class="tm-row" role="radiogroup" aria-label="Theme">' +
            '<button type="button" class="tm-opt" role="radio" data-mode="light">' + ICON.sun + 'Light</button>' +
            '<button type="button" class="tm-opt" role="radio" data-mode="dark">' + ICON.moon + 'Dark</button>' +
            '<button type="button" class="tm-opt" role="radio" data-mode="system">' + ICON.auto + 'Auto</button>' +
            '</div>';
        document.body.appendChild(pop);
        refreshActive();
        position();
        pop.addEventListener('click', function (e) {
            var o = e.target.closest('.tm-opt');
            if (!o) return;
            var origin = popBtn;
            var m = o.dataset.mode;
            teardown(false);
            setMode(m, origin);
        });
        document.addEventListener('keydown', onKey, true);
        window.addEventListener('resize', onResize);
        var cur = pop.querySelector('.tm-opt[aria-checked="true"]');
        if (cur) cur.focus();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-theme-menu]');
        if (btn) {
            e.preventDefault();
            if (pop && popBtn === btn) teardown(true);
            else { if (pop) teardown(false); open(btn); }
            return;
        }
        if (pop && !e.target.closest('.tm-pop')) teardown(false);
    });

    if (mq) {
        var onChange = function () { if (mode() === 'system') apply('system'); };
        if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
    }

    apply(mode());
})();
