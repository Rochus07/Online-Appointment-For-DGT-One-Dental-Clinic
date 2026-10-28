/* ============================================================
   Core client helpers: API wrapper, modal, toast, clock, menus
   ============================================================ */
(function () {
  'use strict';

  const BASE = '/dgt_dental_clinic';

  /* ---------- Tailwind component class strings ----------
     Same base-string + colour-suffix pattern the module pages use: the base
     carries layout only, the suffix carries bg/border/text. Exposed on
     window.UI for the handful of $PAGE_SCRIPT blocks that build the same bits
     inline. Marker classes JS still queries (modal-head / modal-body /
     modal-foot / modal-close, notif, field-err, is-invalid) are kept as the
     leading token; .field-err / .is-invalid keep their rules in tailwind.php. */
  const OVERLAY   = 'absolute inset-0 bg-navy-900/55';
  const MODAL     = 'relative mx-auto my-3 mb-6 w-[min(640px,calc(100%-16px))] sm:my-[6vh] sm:mb-10 sm:w-[min(640px,calc(100%-32px))] overflow-hidden rounded-xl bg-white shadow-[0_18px_45px_rgba(15,28,46,0.22)] dark:bg-navy-800';
  const MODAL_WIDE = ' !w-[min(880px,calc(100%-16px))] sm:!w-[min(880px,calc(100%-32px))]';
  const M_HEAD    = 'modal-head flex items-center justify-between gap-2.5 border-b border-steel-100 px-4 py-3.5 sm:px-5 sm:py-4 dark:border-white/10';
  const M_TITLE   = 'mr-auto text-base text-navy-900 dark:text-steel-50';
  const M_CLOSE   = 'modal-close cursor-pointer border-0 bg-transparent text-[22px] leading-none text-steel-400 hover:text-navy-800 dark:hover:text-steel-50';
  const M_BODY    = 'modal-body max-h-[calc(100dvh-150px)] overflow-y-auto p-4 sm:max-h-[66vh] sm:p-5';
  const M_FOOT    = 'modal-foot flex flex-wrap justify-end gap-2.5 border-t border-steel-100 px-4 py-3 sm:px-5 sm:py-3.5 dark:border-white/10';

  const TOAST_ROOT = 'toast-root fixed bottom-[18px] right-[18px] z-[200] flex flex-col gap-2.5';
  const TOAST     = 'max-w-[320px] rounded-md border-l-4 bg-navy-800 px-4 py-3 text-[13px] text-white shadow-[0_6px_20px_rgba(15,28,46,0.12)]';
  const TOAST_C   = { ok: ' border-ok', err: ' border-danger', info: ' border-accent' };

  const BADGE     = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const BADGE_C   = {
    // Standardized appointment-status colours (see /DESIGN.md):
    //   Pending = yellow · Approved = blue · Completed = green ·
    //   Rescheduled = fuchsia · Cancelled = red. (There is no "Ongoing" status.)
    // Dark-mode pairs swap the pale "-soft" tint (designed for a white card)
    // for the same accent at ~20% opacity as the fill, keeping the accent
    // itself as the text colour — the common "badge on a dark surface" trick.
    pending: ' bg-warn-soft text-warn dark:bg-warn/20', approved: ' bg-info-soft text-info dark:bg-info/20',
    completed: ' bg-ok-soft text-ok dark:bg-ok/20', cancelled: ' bg-danger-soft text-danger dark:bg-danger/20',
    rescheduled: ' bg-[#fae8ff] text-[#a21caf] dark:bg-[#a21caf]/25 dark:text-[#f0abfc]',
    // Payment Declined (2026-09) — a recoverable rejection of just the online
    // reservation receipt, not a cancellation, so it needs its own colour
    // distinct from both Pending (yellow) and Cancelled (red) — a one-off
    // burnt-orange hex, same "entity colour outside the standard token set"
    // exception already granted to src-online/src-walkin below.
    payment_declined: ' bg-[#fdecd9] text-[#b3540c] dark:bg-[#b3540c]/25 dark:text-[#f5c99a]',
    // No Show (migration 064): the patient never checked in; a muted plum so it
    // reads apart from Cancelled (red) and Rescheduled (fuchsia).
    no_show: ' bg-[#ece8f6] text-[#5b4a8b] dark:bg-[#5b4a8b]/30 dark:text-[#cfc4ef]',
    // Grace period (migration 088): Delayed / Standby (amber-orange) and
    // Arrived (Late) (teal), both derived from delayed_at + checked_in_at.
    delayed: ' bg-[#ffedd5] text-[#c2410c] dark:bg-[#c2410c]/25 dark:text-[#fdba74]',
    arrived_late: ' bg-[#ccfbf1] text-[#0f766e] dark:bg-[#0f766e]/30 dark:text-[#5eead4]',
    unpaid: ' bg-danger-soft text-danger dark:bg-danger/20', partially_paid: ' bg-warn-soft text-warn dark:bg-warn/20',
    fully_paid: ' bg-ok-soft text-ok dark:bg-ok/20',
    'billing-pending': ' bg-warn-soft text-warn dark:bg-warn/20', 'billing-paid': ' bg-ok-soft text-ok dark:bg-ok/20',
    'src-online': ' bg-[#ede9fe] text-[#6d28d9] dark:bg-[#6d28d9]/30 dark:text-[#c4b5fd]',   // violet (2026-10-11; indigo looked like Approved blue, slate was too plain)
    'src-walkin': ' bg-[#d7f2ea] text-[#0f766e] dark:bg-[#0f766e]/25 dark:text-[#8fe0d0]',
    on: ' bg-ok-soft text-ok dark:bg-ok/20', off: ' bg-steel-100 text-steel-500 dark:bg-white/10 dark:text-steel-300',
    absent: ' bg-danger-soft text-danger dark:bg-danger/20',
    // Assistant Dentists on Duty drill-down (admin dashboard) — whether an
    // on-duty assistant currently has any checked-in patients assigned to them.
    available: ' bg-ok-soft text-ok dark:bg-ok/20', busy: ' bg-warn-soft text-warn dark:bg-warn/20',
  };

  const ALERT     = 'mb-3.5 rounded-md px-3.5 py-[11px] text-[13px]';
  const ALERT_C   = {
    error: ' bg-danger-soft text-danger dark:bg-danger/15', ok: ' bg-ok-soft text-ok dark:bg-ok/15',
    info: ' bg-info-soft text-info dark:bg-info/15', warn: ' bg-warn-soft text-warn dark:bg-warn/15',
  };

  const KV        = 'grid grid-cols-[minmax(0,1fr)] text-[14px] [&_dt]:text-[11px] [&_dt]:font-semibold [&_dt]:uppercase [&_dt]:tracking-wide [&_dd]:mb-3 [&_dd]:break-words sm:grid-cols-[150px_1fr] sm:gap-x-3.5 sm:gap-y-1.5 sm:text-[13px] sm:[&_dt]:text-[13px] sm:[&_dt]:font-normal sm:[&_dt]:normal-case sm:[&_dt]:tracking-normal sm:[&_dd]:mb-0 [&_dt]:text-steel-400 [&_dd]:font-medium [&_dd]:text-navy-800 [&_dd]:dark:text-steel-50';
  const ROW_BTW   = 'flex items-center justify-between gap-3';
  const BIG_NUM   = 'text-xl font-bold text-navy-900 dark:text-steel-50';
  const MUTED     = 'text-steel-400';
  const HINT      = 'text-[11px] text-steel-400';
  const SEP       = 'my-4 border-0 border-t border-steel-100 dark:border-white/10';
  const STACK     = 'flex flex-col gap-1.5';
  const CHECK_LINE = 'flex items-center gap-2 text-[13px]';
  const TABLE     = 'w-full border-collapse text-[13px] [&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 [&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10';
  const BTN       = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
  const BTN_GHOST = BTN + ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const BTN_OK    = BTN + ' bg-ok text-white hover:brightness-95';

  window.UI = {
    BADGE, BADGE_C, ALERT, ALERT_C, KV, ROW_BTW, BIG_NUM, MUTED, HINT, SEP, STACK, CHECK_LINE, TABLE,
    badgeHTML: (text, kind) => '<span class="' + BADGE + (BADGE_C[kind] || '') + '">' + window.esc(String(text)) + '</span>',
    alertCls: (kind) => ALERT + (ALERT_C[kind] || ALERT_C.info),
  };
  window.badgeHTML = window.UI.badgeHTML;
  window.alertCls  = window.UI.alertCls;

  /* ---------- Network feedback (2026-10-18) ----------
     One layer for every page, so actions never look frozen:
     - #netBar: a thin top progress bar while a page loads (nav.js) or an
       action is saving. It only appears after 200 ms, so fast calls don't flash.
     - The button that started an action (clicked < 1.5 s before a POST to
       /api/) gets aria-busy="true": a spinner, and further clicks on it are
       ignored until the request ends, so nothing is sent twice. `disabled`
       is never touched, so page code that manages it keeps working.
     - window.api() gives up after 30 s and turns a dropped connection into a
       readable message; going offline / back online shows a toast. */
  const NET = { n: 0, timer: 0 };
  function netBar() {
    let b = document.getElementById('netBar');
    if (!b) {
      b = document.createElement('div');
      b.id = 'netBar';
      b.setAttribute('aria-hidden', 'true');
      document.body.appendChild(b);
    }
    return b;
  }
  window.netBusy = function (on) {
    NET.n = Math.max(0, NET.n + (on ? 1 : -1));
    clearTimeout(NET.timer);
    if (NET.n > 0) {
      NET.timer = setTimeout(() => { if (NET.n > 0) netBar().classList.add('on'); }, 200);
    } else if (document.getElementById('netBar')) {
      netBar().classList.remove('on');
    }
  };
  const lastPress = { el: null, t: 0 };
  document.addEventListener('click', (e) => {
    const b = e.target.closest('button, [role="button"], input[type="submit"]');
    if (!b) return;
    if (b.getAttribute('aria-busy') === 'true') {   // still saving: swallow repeat clicks
      e.preventDefault();
      e.stopImmediatePropagation();
      return;
    }
    lastPress.el = b; lastPress.t = Date.now();
  }, true);
  function busyButton() {
    const b = lastPress.el;
    if (!b || !b.isConnected || Date.now() - lastPress.t > 1500 || b.hasAttribute('data-close')) return null;
    b._busyN = (b._busyN || 0) + 1;
    b.setAttribute('aria-busy', 'true');
    return b;
  }
  function releaseButton(b) {
    if (!b) return;
    b._busyN = Math.max(0, (b._busyN || 1) - 1);
    if (b._busyN === 0) b.removeAttribute('aria-busy');
    // A handler that chains a second call (save, then upload) stays busy.
    if (lastPress.el === b) lastPress.t = Date.now();
  }
  // Every fetch to /api/ (window.api() and the few raw upload fetches) is tracked.
  const rawFetch = window.fetch.bind(window);
  window.fetch = function (input, init) {
    const url = typeof input === 'string' ? input : (input && input.url) || '';
    const method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
    if (url.indexOf('/api/') === -1 || method === 'GET') return rawFetch(input, init);
    const btn = busyButton();
    window.netBusy(true);
    return rawFetch(input, init).finally(() => { window.netBusy(false); releaseButton(btn); });
  };
  window.addEventListener('offline', () => window.toast && window.toast('You are offline. Changes will not save until the connection is back.', 'err'));
  window.addEventListener('online', () => window.toast && window.toast('Back online.', 'info'));

  /* ---------- API ---------- */
  const API_TIMEOUT_MS = 30000;
  window.api = async function (path, { method = 'GET', body = null } = {}) {
    const ctl = typeof AbortController === 'function' ? new AbortController() : null;
    const opts = {
      method,
      headers: { 'X-Requested-With': 'fetch' },
    };
    if (ctl) opts.signal = ctl.signal;
    if (body !== null) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    const kill = ctl ? setTimeout(() => ctl.abort(), API_TIMEOUT_MS) : 0;
    let res;
    try {
      res = await fetch(BASE + '/api/' + path, opts);
    } catch (err) {
      clearTimeout(kill);
      throw new Error(err && err.name === 'AbortError'
        ? 'The server is taking too long to respond. Please try again.'
        : (navigator.onLine === false
          ? 'You are offline. Check your connection and try again.'
          : 'Could not reach the server. Check your connection and try again.'));
    }
    let data;
    try { data = await res.json(); } catch { data = { ok: false, error: 'Bad server response' }; }
    finally { clearTimeout(kill); }
    if (!res.ok || data.ok === false) {
      throw new Error(data.error || ('Request failed (' + res.status + ')'));
    }
    return data;
  };

  /* ---------- Toast ---------- */
  window.toast = function (msg, type = 'ok') {
    let root = document.getElementById('toastRoot');
    if (!root) {
      root = document.createElement('div');
      root.id = 'toastRoot';
      root.className = TOAST_ROOT;
      root.setAttribute('aria-live', 'polite');
      root.setAttribute('aria-atomic', 'false');
      document.body.appendChild(root);
    }
    const el = document.createElement('div');
    el.className = TOAST + (type === 'err' ? TOAST_C.err : type === 'info' ? TOAST_C.info : TOAST_C.ok);
    // role="alert" is assertive (interrupts) for errors; "status" is polite.
    el.setAttribute('role', type === 'err' ? 'alert' : 'status');
    el.textContent = msg;
    root.appendChild(el);
    setTimeout(() => el.remove(), 4000);
  };

  /* ---------- Modal ---------- */
  const root = () => document.getElementById('modalRoot');
  const prefersReduced = () =>
    window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // Bumped for every pane's <h3> so ids never collide.
  let modalSeq = 0;
  // The element focus returns to when the modal fully closes (DESIGN.md → Accessibility).
  let modalReturnFocus = null;
  // Back-stack for "a view opened on top of another inside the same modal"
  // (e.g. a dashboard drill-down table, then clicking a row's
  // appointmentModal() on top of it). The overlay + dialog wrapper is never
  // unmounted for this — only the dialog's inner *pane* (its modalShell
  // content) slides out to one side and a new pane slides in, carousel-style
  // (see slidePanes() below), so there is exactly one persistent modal
  // container throughout the whole drill-down → detail → back workflow, not
  // two separate modal instances swapped for one another. Each stack entry
  // is the actual detached previous `.modal-pane` node — retained, not
  // rebuilt from an html string, so anything that mutated it after it was
  // first shown (an async-loaded table, a per-row toggle button's own
  // listener) survives the round trip. A caller opts into this with
  // openModal(html, {stack: true}); any other open (the default) tears the
  // stack down and starts a fresh dialog, since it isn't a view layered on
  // top of what's showing. The pushed-in pane's own "← Back" button (see
  // addBackButton()) steps back exactly one level via window.modalBack();
  // the X button / Esc / backdrop click (window.closeModal()) always closes
  // the whole workflow instead, from any pane, regardless of stack depth —
  // deliberately two different affordances, not the same action. Each pushed
  // level's own `wide` option DOES take effect — the dialog's width smoothly
  // animates (dialogTargetWidth() / animateDialogWidth() below) from
  // whatever it was to that level's own target width alongside the pane
  // slide, rather than freezing at the bottom level's width for the whole
  // stack; every `modalStack` entry remembers the width it's stepping away
  // from, so window.modalBack() animates back to exactly that on the way
  // out. This also has to actively re-lock the width (not just leave it to
  // the dialog's own class), since a `:has()` CSS rule keying off pane
  // content can otherwise re-widen/narrow it the instant the outgoing pane's
  // node is removed once the slide settles.
  let modalStack = [];
  let stackNext = false;
  window.stackNextModal = (on = true) => { stackNext = !!on; };
  window.modalStackDepth = () => modalStack.length;
  // True while a pane slide (push or back) is animating — ignores a second
  // push/back click until it settles, so two rapid clicks can't corrupt the
  // track mid-transition.
  let paneBusy = false;
  const PANE_MS = 320;
  const MODAL_FOCUSABLE =
    'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

  // Keep Tab inside the open dialog. Attached once per dialog lifetime (the
  // dialog element itself persists across a whole stack of pane pushes/pops),
  // and re-queries MODAL_FOCUSABLE fresh on every keydown, so it always scopes
  // to whichever pane is currently mounted with no re-wiring needed per pane.
  function trapTab(e) {
    if (e.key !== 'Tab') return;
    const dlg = e.currentTarget;
    const items = Array.prototype.slice
      .call(dlg.querySelectorAll(MODAL_FOCUSABLE))
      .filter((el) => el.offsetWidth || el.offsetHeight || el === document.activeElement);
    if (!items.length) { e.preventDefault(); dlg.focus(); return; }
    const first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  // Builds one `.modal-pane` — the dialog's inner content for one "view" —
  // from modalShell() html: wires its own [data-close] buttons and stamps a
  // unique id on its <h3> (for aria-labelledby).
  function buildPane(html) {
    const pane = document.createElement('div');
    pane.className = 'modal-pane w-full shrink-0';
    pane.innerHTML = html;
    pane.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', window.closeModal));
    const title = pane.querySelector('h3');
    if (title && !title.id) title.id = 'modalTitle' + (++modalSeq);
    return pane;
  }

  // Points the persistent dialog's aria-labelledby at whichever pane is
  // currently the visible one, and moves focus into the dialog (WAI-ARIA
  // APG: the dialog itself is a valid target for a view/step change).
  function focusPane(dlg, pane) {
    const title = pane.querySelector('h3');
    if (title) dlg.setAttribute('aria-labelledby', title.id);
    dlg.focus({ preventScroll: true });
  }

  // Restructures a newly-pushed pane's head into a 3-column layout — a plain
  // "← Back" button (top-left, calls window.modalBack() — steps back one
  // level; distinct from the X button, which always calls window.closeModal()
  // and closes the whole workflow from any pane), the pane's own <h3> title
  // (centered), and its existing X close button (top-right, untouched).
  // Only ever added when there is somewhere to go back to, i.e. openModal was
  // actually called with {stack: true} on top of something already showing.
  // "← Back to <title>" (naming the previous view) was tried first and
  // dropped for a plain "← Back" — with a longer previous title, it crowded
  // the header against the new title next to it.
  function addBackButton(pane) {
    const head = pane.querySelector('.modal-head');
    const title = head && head.querySelector('h3');
    if (!head || !title) return;
    head.classList.remove('flex', 'justify-between');
    head.classList.add('grid', 'grid-cols-[auto_1fr_auto]');
    title.classList.remove('mr-auto');
    title.classList.add('text-center');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'modal-back shrink-0 whitespace-nowrap text-[13px] font-semibold text-accent hover:underline dark:text-dgt-blue-300';
    btn.textContent = '← Back';
    btn.addEventListener('click', window.modalBack);
    head.insertBefore(btn, title);
  }

  // Slides the dialog's track from `fromPane` to `toPane` (both already
  // mounted as siblings in the track) and resizes the shared viewport to
  // `toPane`'s natural height over the same transition — `dir` is 1 to go
  // forward (a new view opening on top, entering from the right) or -1 to go
  // back (returning to the previous view, entering from the left). Leaves
  // the track/viewport back in their plain single-pane steady state (no
  // leftover inline width/transform/height) once settled, so the next
  // transition always starts from a clean slate.
  function slidePanes(viewport, track, fromPane, toPane, dir) {
    return new Promise((resolve) => {
      if (prefersReduced()) {
        fromPane.remove();
        track.style.cssText = '';
        toPane.style.cssText = '';
        viewport.style.cssText = 'overflow: hidden';
        resolve();
        return;
      }
      track.style.transition = 'none';
      viewport.style.transition = 'none';
      track.style.width = '200%';
      fromPane.style.width = '50%';
      toPane.style.width = '50%';
      // Lock the current position/height instantly first — this exactly
      // reproduces the plain single-pane state we're animating away from, no
      // visible change yet.
      track.style.transform = dir > 0 ? 'translateX(0%)' : 'translateX(-50%)';
      viewport.style.height = fromPane.offsetHeight + 'px';
      void viewport.offsetWidth;   // flush the instant "start" state above
      track.style.transition = 'transform ' + PANE_MS + 'ms cubic-bezier(.16,1,.3,1)';
      viewport.style.transition = 'height ' + PANE_MS + 'ms cubic-bezier(.16,1,.3,1)';
      track.style.transform = dir > 0 ? 'translateX(-50%)' : 'translateX(0%)';
      viewport.style.height = toPane.offsetHeight + 'px';
      const done = () => {
        track.removeEventListener('transitionend', onEnd);
        clearTimeout(fallback);
        fromPane.remove();
        track.style.cssText = '';
        toPane.style.cssText = '';
        viewport.style.cssText = 'overflow: hidden';
        resolve();
      };
      const onEnd = (e) => { if (e.target === track && e.propertyName === 'transform') done(); };
      track.addEventListener('transitionend', onEnd);
      const fallback = setTimeout(done, PANE_MS + 80);   // fallback if transitionend never fires
    });
  }

  // The pixel width a pane at this stack level actually wants — mirrors the
  // MODAL / MODAL_WIDE Tailwind classes' own `min(640px|880px, 100% - 32px)`
  // formula, computed in JS since a stacked pane has no width class of its
  // own (only the persistent dialog element does — see the modalStack
  // comment above).
  function dialogTargetWidth(wide) {
    return Math.min(wide ? 880 : 640, window.innerWidth - (window.innerWidth < 640 ? 16 : 32));
  }

  // Smoothly resizes the persistent dialog from `fromPx` to `toPx` alongside
  // a pane push/pop, instead of snapping — same duration/easing as
  // slidePanes()'s own transform/height animation, so the frame visibly
  // widens or narrows in step with the content sliding in. Leaves the width
  // locked at `toPx` (still `!important`) once done, exactly like the old
  // fixed lock did, so a `:has()` CSS rule reacting to the outgoing pane's
  // own removal can't re-trigger a secondary snap.
  function animateDialogWidth(dlg, fromPx, toPx) {
    if (prefersReduced() || Math.round(fromPx) === Math.round(toPx)) {
      dlg.style.setProperty('width', toPx + 'px', 'important');
      return;
    }
    dlg.style.transition = 'none';
    dlg.style.setProperty('width', fromPx + 'px', 'important');
    void dlg.offsetWidth;   // flush the instant "start" state above
    dlg.style.transition = 'width ' + PANE_MS + 'ms cubic-bezier(.16,1,.3,1)';
    dlg.style.setProperty('width', toPx + 'px', 'important');
    const clear = () => { dlg.style.transition = ''; dlg.removeEventListener('transitionend', onEnd); clearTimeout(fallback); };
    const onEnd = (e) => { if (e.target === dlg && e.propertyName === 'width') clear(); };
    dlg.addEventListener('transitionend', onEnd);
    const fallback = setTimeout(clear, PANE_MS + 80);
  }

  window.openModal = function (html, { wide = false, stack = false } = {}) {
    // window.stackNextModal(): the next open slides in on top of what's showing.
    if (stackNext) { stack = true; stackNext = false; }
    const r = root();
    const isOpen = r && !r.hidden && r.children.length > 0;
    if (isOpen && stack && !paneBusy) {
      const dlg = r.querySelector('[role="dialog"]');
      const viewport = dlg && dlg.querySelector('.modal-viewport');
      const track = viewport && viewport.querySelector('.modal-track');
      const curPane = track && track.querySelector(':scope > .modal-pane');
      if (dlg && viewport && track && curPane) {
        // Remember the width we're stepping away from (so modalBack() can
        // animate straight back to it) and smoothly resize the dialog to
        // this pushed pane's own target width alongside the slide, rather
        // than freezing at whatever the outgoing pane's width was — see the
        // modalStack comment above and animateDialogWidth()'s own comment
        // for why the width still needs actively locking (not just left to
        // the dialog's class) once the outgoing pane's node is removed.
        const startW = Math.round(dlg.getBoundingClientRect().width);
        modalStack.push({ pane: curPane, width: startW });
        animateDialogWidth(dlg, startW, dialogTargetWidth(wide));
        const newPane = buildPane(html);
        addBackButton(newPane);
        track.appendChild(newPane);
        paneBusy = true;
        slidePanes(viewport, track, curPane, newPane, 1).then(() => {
          paneBusy = false;
          focusPane(dlg, newPane);
        });
        return;
      }
      // Fall through to a fresh open below if the current modal somehow
      // isn't in the expected pane shape — every openModal builds it this
      // way, so this shouldn't happen, but never leave the app stuck on it.
    }
    if (isOpen && stack && paneBusy) return;   // a pane slide is mid-flight — ignore
    // A fresh flow (not layered on what's showing) — any stale back-stack
    // from an earlier, unrelated modal chain is discarded, and the whole
    // overlay + dialog is (re)built from scratch.
    modalStack = [];
    modalReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const pane = buildPane(html);
    r.innerHTML =
      '<div class="' + OVERLAY + '" data-close></div>' +
      '<div class="' + MODAL + (wide ? MODAL_WIDE : '') + '" role="dialog" aria-modal="true" tabindex="-1">' +
      '<div class="modal-viewport overflow-hidden"><div class="modal-track flex items-start w-full"></div></div>' +
      '</div>';
    // A click outside the dialog closes it only while nothing has been typed
    // or picked yet; otherwise the form stays open (2026-10-17).
    r.firstElementChild.addEventListener('click', () => {
      if (r.dataset.dirty === '1') { nudgeDirtyModal(r); return; }
      window.closeModal('backdrop');
    });
    r.dataset.dirty = '';
    if (!r.dataset.dirtyWired) {
      r.dataset.dirtyWired = '1';
      const markDirty = (e) => {
        if (!e.isTrusted || r.hidden) return;
        const t = e.target;
        if (!t || !t.closest || !t.closest('[role="dialog"]')) return;
        if (e.type === 'click' && !t.closest('.pcal-cell, [role="option"], .pm-card')) return;
        r.dataset.dirty = '1';
      };
      r.addEventListener('input', markDirty);
      r.addEventListener('change', markDirty);
      r.addEventListener('click', markDirty);
    }
    r.querySelector('.modal-track').appendChild(pane);
    r.hidden = false;
    document.body.style.overflow = 'hidden';
    const dlg = r.querySelector('[role="dialog"]');
    dlg.addEventListener('keydown', trapTab);
    r.classList.remove('is-in');
    void r.offsetWidth;
    r.classList.add('is-in');
    focusPane(dlg, pane);
  };

  // A click outside a form with entries is ignored; the dialog just gives a
  // small shake (no message, 2026-10-17).
  function nudgeDirtyModal(r) {
    const dlg = r.querySelector('[role="dialog"]');
    if (dlg && !prefersReduced() && dlg.animate) {
      dlg.animate(
        [{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' },
         { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' }],
        { duration: 320, easing: 'ease-out' }
      );
    }
  }

  // Steps back exactly one level in the current modal (the "← Back" button
  // addBackButton() adds to a stacked pane's head) — distinct from
  // window.closeModal(), which always closes the whole workflow regardless
  // of how many levels deep it is. A no-op while a slide is already in
  // flight, or if there's nowhere to go back to.
  window.modalBack = function () {
    const r = root();
    if (!r || r.hidden || paneBusy || !modalStack.length) return;
    const dlg = r.querySelector('[role="dialog"]');
    const viewport = dlg && dlg.querySelector('.modal-viewport');
    const track = viewport && viewport.querySelector('.modal-track');
    const curPane = track && track.querySelector(':scope > .modal-pane');
    const entry = modalStack[modalStack.length - 1];
    const prevPane = entry && entry.pane;
    if (!dlg || !viewport || !track || !curPane || !prevPane) { modalStack = []; return; }
    modalStack.pop();
    track.insertBefore(prevPane, curPane);
    paneBusy = true;
    animateDialogWidth(dlg, Math.round(dlg.getBoundingClientRect().width), entry.width);
    slidePanes(viewport, track, curPane, prevPane, -1).then(() => {
      paneBusy = false;
      if (!modalStack.length) dlg.style.removeProperty('width');   // back at the bottom — hand control back to CSS/classes
      focusPane(dlg, prevPane);
    });
  };

  // Always closes the whole modal from any pane (X button, Esc, backdrop
  // click) — it does not step back through the stack one level at a time;
  // that is window.modalBack()'s job (the pane's own "← Back" button).
  // reason: 'button' (an X / Back / Cancel [data-close] click), 'backdrop',
  // 'escape', or none (code closing it, e.g. moving between wizard steps).
  window.closeModal = function (arg) {
    const reason = typeof arg === 'string' ? arg
      : (arg && arg.target && arg.target.closest && arg.target.closest('[data-close]')) ? 'button' : '';
    const r = root();
    if (!r || r.hidden || paneBusy) return;
    modalStack = [];
    const mySeq = ++modalSeq;
    if (window.closeSlotPopup) window.closeSlotPopup('modal');
    // Lets a page react to the user closing a modal (the booking wizard
    // drops its saved draft on a deliberate close; a page reload never fires this).
    document.dispatchEvent(new CustomEvent('dgt:modalclose', { detail: { reason } }));
    const finish = () => {
      if (mySeq !== modalSeq) return;   // a newer modal opened — leave it alone
      r.hidden = true;
      r.innerHTML = '';
      r.classList.remove('is-in');
      document.body.style.overflow = '';
      if (modalReturnFocus && document.contains(modalReturnFocus)) {
        try { modalReturnFocus.focus({ preventScroll: true }); } catch (e) { /* element gone */ }
      }
      modalReturnFocus = null;
    };
    r.classList.remove('is-in');
    const dialog = r.querySelector('[role="dialog"]');
    if (prefersReduced() || !dialog) { finish(); return; }
    dialog.addEventListener('transitionend', function onEnd(e) {
      if (e.target !== dialog) return;
      dialog.removeEventListener('transitionend', onEnd);
      finish();
    });
    setTimeout(finish, 300);   // fallback if transitionend never fires
  };

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && root() && !root().hidden) window.closeModal('escape');
  });

  /* ---------- Time-slot pop-up (2026-10-10, adviser feedback) ----------
     Picking a date on a booking calendar opens this centred pop-up with that
     day's times, instead of listing them further down the page where users
     had to scroll to find them. It sits above any open modal (z-130) and is
     appended to <body>, so the booking wizard's own dialog stays untouched
     underneath. Picking a time calls onPick straight away; Confirm, the X, the
     backdrop and Escape all just close it (the pick is kept).
       openSlotPopup({ title, note, slots: [{ slot, label, sub, free }],
                       selected, confirmLabel, onPick(slot), onConfirm(slot),
                       onClose(reason) })                                     */
  let slotPop = null;
  window.closeSlotPopup = function (reason) {
    const p = slotPop;
    if (!p) return;
    slotPop = null;
    document.removeEventListener('keydown', p.onKey, true);
    const el = p.el;
    const done = () => el.remove();
    if (prefersReduced()) done();
    else { el.style.opacity = '0'; el.firstElementChild.style.transform = 'translateY(8px) scale(.97)'; setTimeout(done, 170); }
    if (p.returnTo && document.contains(p.returnTo)) {
      try { p.returnTo.focus({ preventScroll: true }); } catch (e) { /* gone */ }
    }
    if (p.onClose) p.onClose(reason || 'close');
  };
  // After a time is picked in a reschedule pop-up, bring the Reason box into
  // view (waits for the slot pop-up to close). Focus only with a mouse, so a
  // phone keyboard doesn't jump up.
  window.scrollToReason = function (id) {
    setTimeout(() => {
      const el = document.getElementById(id);
      if (!el || !el.isConnected) return;
      const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
      el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
      if (!matchMedia('(pointer: coarse)').matches) el.focus({ preventScroll: true });
    }, 450);
  };

  // Small message pop-up shown above any open modal (z-140), e.g. a patient
  // clicking a calendar day they already have an appointment on (2026-10-18).
  window.noticePopup = function (title, msg) {
    const esc = window.esc;
    const el = document.createElement('div');
    el.className = 'tooth-pop fixed inset-0 z-[140] flex items-center justify-center bg-[rgba(15,28,46,.55)] p-4';
    el.setAttribute('role', 'alertdialog');
    el.setAttribute('aria-modal', 'true');
    el.innerHTML =
      '<div class="w-full max-w-[640px] overflow-hidden rounded-xl bg-white shadow-xl dark:bg-dgt-ink-800">' +
        '<div class="flex items-center justify-between gap-3 border-b border-dgt-ink-200 px-5 py-4 dark:border-white/10">' +
          '<h3 class="m-0 text-[16px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(title) + '</h3>' +
          '<button type="button" class="modal-close inline-flex h-9 w-9 items-center justify-center rounded-full text-[22px] leading-none text-dgt-ink-500 hover:bg-dgt-ink-100 dark:text-dgt-ink-300 dark:hover:bg-white/10" data-np-close aria-label="Close">&times;</button>' +
        '</div>' +
        '<p class="m-0 px-5 py-5 text-[14px] leading-relaxed text-dgt-ink-700 dark:text-dgt-ink-200">' + esc(msg) + '</p>' +
        '<div class="flex justify-end border-t border-dgt-ink-200 px-5 py-4 dark:border-white/10">' +
          '<button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md bg-dgt-teal-600 px-[15px] py-[9px] text-[13px] font-semibold text-white hover:bg-dgt-teal-700" data-np-close>OK</button>' +
        '</div>' +
      '</div>';
    const close = () => { el.remove(); document.removeEventListener('keydown', onKey, true); };
    const onKey = (e) => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
    el.addEventListener('click', (e) => { if (e.target === el || e.target.closest('[data-np-close]')) close(); });
    document.addEventListener('keydown', onKey, true);
    document.body.appendChild(el);
    const ok = el.querySelectorAll('[data-np-close]'); ok[ok.length - 1].focus();
  };
  window.dayTakenPopup = (other) => window.noticePopup('Appointment Already Booked', other
    ? 'Your child already has an appointment on this day. Only one appointment per day is allowed for each person, so please choose another date.'
    : 'You already have an appointment on this day. Only one appointment per day is allowed for each person, so please choose another date. To book for your child on this day, use Request Appointment and choose For My Child.');

  // "1 of 2 slots left" under a free hour in the time pop-up (2026-10-26).
  window.slotsLeftText = function (n) {
    const cap = window.SLOT_CAP || 2;
    n = Math.max(0, Math.min(+n || 0, cap));
    return n + ' of ' + cap + ' slot' + (cap === 1 ? '' : 's') + ' left';
  };

  window.openSlotPopup = function (o) {
    if (slotPop) window.closeSlotPopup('replace');
    const esc = window.esc;
    let picked = o.selected || '';
    const anyFree = (o.slots || []).some((s) => s.free);
    const slotsHtml = (o.slots || []).map((s) => s.free
      ? '<button type="button" class="pcal-slot free' + (s.slot === picked ? ' selected-slot' : '') + '" data-pop-slot="' + esc(s.slot) + '" data-sub="' + esc(s.sub || 'Available') + '" aria-pressed="' + (s.slot === picked) + '">' +
          esc(s.label) + '<small>' + (s.slot === picked ? '&#10003; Selected' : esc(s.sub || 'Available')) + '</small></button>'
      : '<span class="pcal-slot taken">' + esc(s.label) + '<small>' + esc(s.sub || 'Occupied') + '</small></span>').join('');
    const el = document.createElement('div');
    el.className = 'slot-pop fixed inset-0 z-[130] flex items-center justify-center bg-[rgba(15,28,46,.55)] p-4';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'slotPopTitle');
    el.innerHTML =
      '<div class="flex max-h-[calc(100dvh-32px)] w-full max-w-[540px] flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-dgt-ink-800">' +
        '<div class="flex items-start justify-between gap-3 border-b border-dgt-ink-200 px-5 py-4 dark:border-white/10">' +
          '<div><p class="m-0 text-[11px] font-semibold uppercase tracking-wide text-dgt-ink-500 dark:text-dgt-ink-400">Choose a time</p>' +
          '<h3 id="slotPopTitle" class="m-0 mt-0.5 text-[16px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(o.title || '') + '</h3></div>' +
          '<button type="button" class="modal-close inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[22px] leading-none text-dgt-ink-500 hover:bg-dgt-ink-100 dark:text-dgt-ink-300 dark:hover:bg-white/10" data-pop-close aria-label="Close">&times;</button>' +
        '</div>' +
        '<div class="overflow-y-auto px-5 py-4">' +
          (o.note ? '<div class="pcal-day-note">' + esc(o.note) + '</div>' : '') +
          (slotsHtml ? '<div class="pcal-slots">' + slotsHtml + '</div>' : '') +
        '</div>' +
        '<div class="flex justify-end gap-2 border-t border-dgt-ink-200 px-5 py-3 dark:border-white/10">' +
          (anyFree
            ? '<button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md bg-dgt-teal-600 px-4 py-2 text-[13px] font-semibold text-white hover:bg-dgt-teal-700 disabled:cursor-not-allowed disabled:opacity-50" data-pop-confirm' + (picked ? '' : ' disabled') + '>' + esc(o.confirmLabel || 'Confirm') + '</button>'
            : '<button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-dgt-ink-200 px-4 py-2 text-[13px] font-semibold text-dgt-ink-700 hover:bg-dgt-ink-50 dark:border-white/15 dark:text-dgt-ink-100 dark:hover:bg-white/5" data-pop-close>Close</button>') +
        '</div>' +
      '</div>';
    const card = el.firstElementChild;
    if (!prefersReduced()) {
      el.style.opacity = '0'; el.style.transition = 'opacity .17s ease';
      card.style.transform = 'translateY(8px) scale(.97)'; card.style.transition = 'transform .2s cubic-bezier(.16,1,.3,1)';
    }
    document.body.appendChild(el);
    if (!prefersReduced()) requestAnimationFrame(() => { el.style.opacity = '1'; card.style.transform = 'none'; });

    const p = { el, onClose: o.onClose, returnTo: document.activeElement };
    p.onKey = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); window.closeSlotPopup('close'); return; }
      if (e.key !== 'Tab') return;
      const items = Array.prototype.slice.call(card.querySelectorAll('button:not([disabled])'));
      if (!items.length) return;
      const first = items[0], last = items[items.length - 1];
      if (!card.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
      else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
    document.addEventListener('keydown', p.onKey, true);
    slotPop = p;

    el.addEventListener('click', (e) => {
      if (e.target === el || e.target.closest('[data-pop-close]')) { window.closeSlotPopup('close'); return; }
      if (e.target.closest('[data-pop-confirm]')) {
        const s = picked;
        window.closeSlotPopup('confirm');
        if (o.onConfirm) o.onConfirm(s);
        return;
      }
      const b = e.target.closest('[data-pop-slot]');
      if (!b) return;
      picked = b.dataset.popSlot;
      card.querySelectorAll('[data-pop-slot]').forEach((x) => {
        const on = x === b;
        x.classList.toggle('selected-slot', on);
        x.setAttribute('aria-pressed', on ? 'true' : 'false');
        const sm = x.querySelector('small');
        if (sm) sm.innerHTML = on ? '&#10003; Selected' : esc(x.dataset.sub || 'Available');
      });
      const c = card.querySelector('[data-pop-confirm]');
      if (c) c.disabled = false;
      if (o.onPick) o.onPick(picked);
    });
    const focusEl = card.querySelector('.selected-slot') || card.querySelector('[data-pop-slot]') || card.querySelector('[data-pop-close]');
    if (focusEl) setTimeout(() => { try { focusEl.focus({ preventScroll: true }); } catch (e) { /* */ } }, 30);
    return p;
  };

  window.modalShell = function (title, bodyHtml, footHtml) {
    return (
      '<div class="' + M_HEAD + '"><h3 class="' + M_TITLE + '">' + title + '</h3>' +
      '<button class="' + M_CLOSE + '" data-close aria-label="Close">&times;</button></div>' +
      '<div class="' + M_BODY + '">' + bodyHtml + '</div>' +
      (footHtml ? '<div class="' + M_FOOT + '">' + footHtml + '</div>' : '')
    );
  };

  /* ---------- Async modal-body loading -> content cross-fade ----------
     A drill-down modal opens immediately with a spinner (this is still one
     single modal instance the whole time — no separate "loading modal" that
     gets closed and replaced), then swaps in its fetched content once the
     request resolves. A raw innerHTML swap here reads as a jarring
     "double pop" whenever a fast local fetch replaces the spinner within a
     few milliseconds of it appearing, so swapModalBody() (1) guarantees the
     loading state is visible for at least MIN_LOADING_MS and (2) cross-fades
     the swap via opacity instead of cutting straight to the new markup.
     Usage: capture `const startedAt = Date.now()` right before opening the
     modal with modalLoadingHtml() as its body, fetch, then
     `await window.swapModalBody(bodyEl, html, startedAt)`. */
  const MODAL_MIN_LOADING_MS = 260;
  const MODAL_FADE_MS = 160;

  window.modalLoadingHtml = function (label) {
    return '<div class="dgt-modal-loading" role="status" aria-live="polite">' +
      '<span class="dgt-spinner" aria-hidden="true"></span>' +
      '<span>' + esc(label || 'Loading…') + '</span></div>';
  };

  window.swapModalBody = async function (bodyEl, html, startedAt) {
    // Bails out cleanly at every await if the modal was closed, or replaced
    // by a newer one, while the fetch was in flight — never writes into a
    // detached node.
    if (!bodyEl || !document.contains(bodyEl)) return;
    const remaining = MODAL_MIN_LOADING_MS - (Date.now() - (startedAt || 0));
    if (remaining > 0) await new Promise((r) => setTimeout(r, remaining));
    if (!document.contains(bodyEl)) return;
    if (prefersReduced()) { bodyEl.innerHTML = html; return; }
    bodyEl.classList.add('dgt-modal-fade-out');
    await new Promise((r) => setTimeout(r, MODAL_FADE_MS));
    if (!document.contains(bodyEl)) return;
    bodyEl.innerHTML = html;
    bodyEl.classList.remove('dgt-modal-fade-out');
  };

  /* ---------- Table pagination (2026-10-17) ----------
     pageSlice(list, state, perPage, nav, onChange) clamps state.page, draws
     "Showing 1–10 of N" + Previous / page numbers / Next into <nav> (hidden
     when one page is enough) and returns { rows, from }. A page click sets
     state.page and calls onChange() to re-render. */
  window.pageSlice = function (list, state, perPage, nav, onChange) {
    const total = list.length;
    const pages = Math.max(1, Math.ceil(total / perPage));
    state.page = Math.min(Math.max(1, state.page || 1), pages);
    const from = (state.page - 1) * perPage;
    const rows = list.slice(from, from + perPage);
    if (nav) {
      if (pages <= 1) { nav.hidden = true; nav.innerHTML = ''; }
      else {
        const base = 'inline-flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-md border px-3 text-[13px] font-semibold ';
        const idle = 'border-steel-200 bg-white text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:bg-navy-800 dark:text-steel-100 dark:hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-40';
        const on = 'border-dgt-blue-900 bg-dgt-blue-900 text-white dark:border-dgt-blue-500 dark:bg-dgt-blue-500';
        const p = state.page, nums = [];
        for (let i = 1; i <= pages; i++) {
          if (i === 1 || i === pages || Math.abs(i - p) <= 1) nums.push(i);
          else if (nums[nums.length - 1] !== '…') nums.push('…');
        }
        nav.innerHTML =
          '<p class="text-[13px] text-steel-500 dark:text-steel-400">Showing ' + (from + 1) + '–' + (from + rows.length) + ' of ' + total + '</p>' +
          '<div class="flex flex-wrap items-center gap-1.5">' +
            '<button type="button" class="' + base + idle + '" data-page="' + (p - 1) + '"' + (p === 1 ? ' disabled' : '') + '>Previous</button>' +
            nums.map((n) => n === '…'
              ? '<span class="px-1 text-steel-400" aria-hidden="true">…</span>'
              : '<button type="button" class="' + base + (n === p ? on : idle) + '" data-page="' + n + '"' +
                  (n === p ? ' aria-current="page"' : '') + ' aria-label="Page ' + n + '">' + n + '</button>').join('') +
            '<button type="button" class="' + base + idle + '" data-page="' + (p + 1) + '"' + (p === pages ? ' disabled' : '') + '>Next</button>' +
          '</div>';
        nav.hidden = false;
        nav.onclick = (e) => {
          const b = e.target.closest('[data-page]');
          if (!b || b.disabled) return;
          state.page = +b.dataset.page;
          onChange();
          const card = nav.parentElement;
          if (card && card.getBoundingClientRect().top < 0) {
            card.scrollIntoView({ block: 'start', behavior: prefersReduced() ? 'auto' : 'smooth' });
          }
        };
      }
    }
    return { rows, from };
  };

  /* Rows per page that fill the screen below a table (min `min`): measured
     from the table's position and a body row's height, so a tall monitor
     shows more rows instead of empty space (2026-10-17). */
  window.fitRows = function (table, min, reserve) {
    min = min || 10;
    if (!table) return min;
    const body = table.tBodies[0];
    const row = body && body.rows[0];
    const rowH = row && row.cells.length > 1 ? row.getBoundingClientRect().height : 46;
    const top = (body || table).getBoundingClientRect().top + window.scrollY;
    // Once measured on real rows, keep the count until the window height or
    // width changes, so every page of the same table shows the same number of
    // rows even when row heights differ (2026-10-18).
    const key = window.innerWidth + 'x' + window.innerHeight;
    if (table.dataset.fitKey === key && table.dataset.fitN) return +table.dataset.fitN;
    const fit = Math.max(min, Math.floor((window.innerHeight - top - (reserve || 115)) / Math.max(30, rowH)));
    if (row && row.cells.length > 1) { table.dataset.fitKey = key; table.dataset.fitN = String(fit); }
    else if (table.dataset.fitAsked !== key) {
      // Only the "Loading…" row was there to measure: re-page once the real rows
      // are in, through the page's resize handler (fitOnResize / Patient Records).
      table.dataset.fitAsked = key;
      setTimeout(() => { const h = window.__fitH || window.__recFit; if (h) h(); }, 0);
    }
    return fit;
  };

  // Re-page a fitRows() table when the window is resized (one page at a time,
  // so a single global handler; the callback should no-op once its table is gone).
  window.fitOnResize = function (fn) {
    clearTimeout(window.__fitT);
    window.removeEventListener('resize', window.__fitH || (() => {}));
    window.__fitH = () => { clearTimeout(window.__fitT); window.__fitT = setTimeout(fn, 150); };
    window.addEventListener('resize', window.__fitH);
  };

  // From / To date pair (2026-10-18): To can't be before From and From can't be
  // after To. The pickers grey out the impossible days (min / max), and a typed
  // reversed date is pulled back to the other end. Call BEFORE the page adds its
  // own change listeners so they see the corrected value. Limits are re-synced
  // on focus too, so a page that clears both fields by code stays correct.
  window.wireDateRange = function (from, to) {
    if (!from || !to) return;
    const sync = () => { to.min = from.value || ''; from.max = to.value || ''; };
    from.addEventListener('change', () => { if (from.value && to.value && from.value > to.value) to.value = from.value; sync(); });
    to.addEventListener('change', () => { if (from.value && to.value && to.value < from.value) from.value = to.value; sync(); });
    [from, to].forEach((el) => el.addEventListener('focus', sync));
    sync();
  };

  /* ---------- Formatting ---------- */
  // A service's usual time (services.duration_minutes): "about 1 hr 30 min",
  // plus " per tooth" for a per-unit service. Mirrors fmtDuration() in functions.php.
  window.fmtDuration = (min, unit) => {
    const m = parseInt(min, 10) || 0;
    if (m <= 0) return '';
    const h = Math.floor(m / 60), r = m % 60;
    return 'about ' + (h ? h + ' hr' + (r ? ' ' + r + ' min' : '') : r + ' min') + (unit ? ' per ' + unit : '');
  };
  window.peso = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  // Money input boxes: <input type="text" inputmode="decimal" data-money>.
  // They show thousands separators while typing (10,000.50); the stored value
  // and every API payload stay a plain number, so always read them with
  // moneyVal() and write them with moneyStr(). data-money="nopad" skips the
  // ".00" added on blur (used for a percentage).
  window.moneyFormat = (raw) => {
    let s = String(raw ?? '').replace(/[^\d.]/g, '');
    const dot = s.indexOf('.');
    let int = dot < 0 ? s : s.slice(0, dot);
    const dec = dot < 0 ? null : s.slice(dot + 1).replace(/\./g, '').slice(0, 2);
    int = int.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return dec === null ? int : (int || '0') + '.' + dec;
  };
  window.moneyVal = (v) => {
    const n = parseFloat(String(v && typeof v === 'object' ? v.value : (v ?? '')).replace(/,/g, ''));
    return isFinite(n) ? n : 0;
  };
  window.moneyStr = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  document.addEventListener('input', (e) => {
    const el = e.target;
    if (!(el instanceof HTMLInputElement) || !el.hasAttribute('data-money')) return;
    const caret = el.selectionStart ?? el.value.length;
    const keep = el.value.slice(0, caret).replace(/[^\d.]/g, '').length;   // digits / dot left of the caret
    const out = window.moneyFormat(el.value);
    if (out === el.value) return;
    el.value = out;
    let pos = 0, seen = 0;
    while (pos < out.length && seen < keep) { if (/[\d.]/.test(out[pos])) seen++; pos++; }
    try { el.setSelectionRange(pos, pos); } catch (_) {}
  }, true);
  document.addEventListener('keydown', (e) => {
    const el = e.target;
    if (el instanceof HTMLInputElement && el.hasAttribute('data-money') && e.key.length === 1
        && !e.ctrlKey && !e.metaKey && !/[\d.]/.test(e.key)) e.preventDefault();
  }, true);
  document.addEventListener('focusout', (e) => {
    const el = e.target;
    if (!(el instanceof HTMLInputElement) || !el.hasAttribute('data-money') || el.readOnly
        || el.getAttribute('data-money') === 'nopad' || el.value.trim() === '') return;
    const s = window.moneyStr(window.moneyVal(el));
    if (s !== el.value) { el.value = s; el.dispatchEvent(new Event('input', { bubbles: true })); }
  }, true);
  window.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  window.fmtDate = (d) => { if (!d) return 'N/A'; const x = new Date(d + 'T00:00:00'); return x.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); };
  // Local calendar date as "YYYY-MM-DD" for <input type="date"> defaults / mins.
  // NOT `new Date().toISOString().slice(0,10)` — that is UTC and reads a day
  // early through the whole PH (UTC+8) morning, which mis-dated payments.
  window.todayISO = (offsetDays = 0) => {
    const d = new Date();
    if (offsetDays) d.setDate(d.getDate() + offsetDays);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  };
  /* ---------- Date of Birth picker (Month / Day / Year, like register) ----------
   * dobSelectsHtml(id, value, inputCls) renders three <select>s plus a hidden
   * input #id that always holds the picked date as "YYYY-MM-DD" (or '' while
   * incomplete), so callers keep reading g(id).value. wireDobSelects(id) keeps
   * the hidden value in sync (firing 'input' on it), trims the Day list to the
   * month (Feb 29 only in leap years) and blocks a future date.
   * setDobSelects(id, value) fills it from code; lockDobSelects(id, on)
   * disables the three dropdowns (also sets #id.readOnly). Any age is allowed:
   * years run from this year back 120 years. */
  const DOB_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  // Whole years from "YYYY-MM-DD" to today (local clock); null if not a past date.
  window.ageFromDob = (dob) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(dob || '');
    if (!m || dob.slice(0, 10) > window.todayISO()) return null;
    const n = new Date();
    let a = n.getFullYear() - +m[1];
    if (n.getMonth() + 1 < +m[2] || (n.getMonth() + 1 === +m[2] && n.getDate() < +m[3])) a--;
    return a >= 0 ? a : null;
  };
  // Saved birthdate => show the computed Age instead of the dropdowns (it
  // updates every year by itself). opts.swap adds that view; once a birthdate
  // is on file it can't be changed from these forms (no Change link, 2026-10-18).
  window.dobSelectsHtml = (id, value, inputCls, opts) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '') || [];
    const y0 = +m[1] || 0, m0 = +m[2] || 0, d0 = +m[3] || 0;
    const swap = !!(opts && opts.swap);
    const showAge = swap && y0 && m0 && d0;
    const ageView = swap
      ? '<div class="flex items-center gap-3" id="' + id + '_agev"' + (showAge ? '' : ' style="display:none"') + '>' +
        '<input class="' + (inputCls || '') + ' min-w-0 flex-1 bg-dgt-ink-50 dark:bg-white/5" id="' + id + '_agetxt" readonly tabindex="-1" aria-label="Age" value="' +
          (showAge ? window.ageFromDob(value) + ' years old' : '') + '"></div>'
      : '';
    // opts.minAge: youngest allowed age (staff = 30); the Year list starts there
    // and a birthdate younger than that never counts as picked.
    const minAge = Math.max(0, +(opts && opts.minAge) || 0);
    // opts.maxAge: oldest allowed age (walk-in new patient = 80); default 120.
    const maxAge = Math.max(minAge, +(opts && opts.maxAge) || 120);
    return ageView + '<div id="' + id + '_pick"' + (showAge ? ' style="display:none"' : '') + '>' +
      window.__dobPickers(id, y0, m0, d0, inputCls, minAge, maxAge) + '</div>' +
      '<input type="hidden" id="' + id + '" data-swap="' + (swap ? '1' : '') + '" data-minage="' + minAge + '" data-maxage="' + maxAge + '" value="' + (y0 && m0 && d0 ? window.esc(value.slice(0, 10)) : '') + '">';
  };
  const dobYearOptions = (y0, minAge, maxAge) => {
    const thisYear = +window.todayISO().slice(0, 4);
    // maxAge + 1: someone whose birthday hasn't come yet this year is still maxAge.
    let years = '';
    for (let y = thisYear - (minAge || 0); y >= thisYear - (maxAge || 120) - 1; y--) years += '<option value="' + y + '"' + (y === y0 ? ' selected' : '') + '>' + y + '</option>';
    return years;
  };
  window.__dobPickers = (id, y0, m0, d0, inputCls, minAge, maxAge) => {
    const cls = (inputCls || '') + ' px-2.5';
    const years = dobYearOptions(y0, minAge, maxAge);
    let days = '';
    for (let d = 1; d <= 31; d++) days += '<option value="' + d + '"' + (d === d0 ? ' selected' : '') + '>' + d + '</option>';
    return '<div class="flex gap-2.5">' +
      '<select class="' + cls + ' min-w-0 flex-[1.5]" id="' + id + '_m" aria-label="Birth month"><option value=""' + (m0 ? '' : ' selected') + ' disabled>Month</option>' +
        DOB_MONTHS.map((n, i) => '<option value="' + (i + 1) + '"' + (i + 1 === m0 ? ' selected' : '') + '>' + n + '</option>').join('') + '</select>' +
      '<select class="' + cls + ' min-w-0 flex-1" id="' + id + '_d" aria-label="Birth day"><option value=""' + (d0 ? '' : ' selected') + ' disabled>Day</option>' + days + '</select>' +
      '<select class="' + cls + ' min-w-0 flex-1" id="' + id + '_y" aria-label="Birth year"><option value=""' + (y0 ? '' : ' selected') + ' disabled>Year</option>' + years + '</select>' +
      '</div>';
  };
  // Show the Age view (birthdate on file) or the dropdowns.
  function dobView(id, ageMode) {
    const av = document.getElementById(id + '_agev'), pk = document.getElementById(id + '_pick');
    if (!av || !pk) return;
    const v = document.getElementById(id).value, a = window.ageFromDob(v);
    const on = ageMode && a !== null;
    av.style.display = on ? '' : 'none';
    pk.style.display = on ? 'none' : '';
    document.getElementById(id + '_agetxt').value = on ? a + ' years old' : '';
    // The field's own label reads "Age" while the Age box shows.
    const lbl = av.parentElement && av.parentElement.querySelector('label');
    if (lbl) {
      if (lbl.dataset.dobHtml == null) lbl.dataset.dobHtml = lbl.innerHTML;
      lbl.innerHTML = on ? 'Age' : lbl.dataset.dobHtml;
    }
  }
  window.dobShowsAge = (id) => {
    const av = document.getElementById(id + '_agev');
    return !!av && av.style.display !== 'none';
  };
  const dobParts = (id) => ['_m', '_d', '_y'].map((s) => document.getElementById(id + s));
  function dobSync(id, fire) {
    const [mS, dS, yS] = dobParts(id), hid = document.getElementById(id);
    if (!mS || !hid) return;
    const y = +yS.value, mo = +mS.value;
    // Days in the chosen month (Feb follows the chosen year; 29 until a year is picked).
    const max = mo ? new Date(y || 2000, mo, 0).getDate() : 31;
    [...dS.options].forEach((o) => { if (o.value) o.disabled = +o.value > max; });
    if (+dS.value > max) dS.value = String(max);
    let v = '';
    if (y && mo && +dS.value) {
      v = y + '-' + String(mo).padStart(2, '0') + '-' + String(+dS.value).padStart(2, '0');
      if (v > window.todayISO()) v = '';   // a future birthdate never counts as picked
      const minAge = +hid.dataset.minage || 0, maxAge = +hid.dataset.maxage || 0;
      if (v && minAge && (window.ageFromDob(v) ?? 0) < minAge) v = '';   // too young
      if (v && maxAge && (window.ageFromDob(v) ?? 0) > maxAge) v = '';   // too old
    }
    hid.value = v;
    if (fire) hid.dispatchEvent(new Event('input', { bubbles: true }));
  }
  window.wireDobSelects = (id) => {
    dobParts(id).forEach((s) => s && s.addEventListener('change', () => dobSync(id, true)));
    dobSync(id, false);
    if (document.getElementById(id + '_agev')) dobView(id, true);
  };
  window.setDobSelects = (id, value) => {
    const [mS, dS, yS] = dobParts(id);
    if (!mS) return;
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
    mS.value = m ? String(+m[2]) : ''; dS.value = m ? String(+m[3]) : ''; yS.value = m ? m[1] : '';
    dobSync(id, false);
    dobView(id, true);   // a saved birthdate shows as Age again
    document.getElementById(id).dispatchEvent(new Event('input', { bubbles: true }));
  };
  // Change the allowed age range of a wired picker (rebuilds the Year list,
  // keeping the picked year when it is still allowed).
  window.setDobRange = (id, minAge, maxAge) => {
    const hid = document.getElementById(id), yS = document.getElementById(id + '_y');
    if (!hid || !yS) return;
    hid.dataset.minage = String(minAge || 0);
    hid.dataset.maxage = String(maxAge || 120);
    const cur = +yS.value || 0;
    yS.innerHTML = '<option value="" disabled>Year</option>' + dobYearOptions(cur, minAge, maxAge);
    if (![...yS.options].some((o) => o.selected && o.value)) yS.value = '';
    dobSync(id, false);
  };
  window.lockDobSelects = (id, on) => {
    dobParts(id).forEach((s) => { if (s) s.disabled = !!on; });
    const hid = document.getElementById(id);
    if (hid) hid.readOnly = !!on;
  };

  // "14:00" / "14:00:00" -> "2:00 PM"
  window.fmtTime = (t) => {
    const m = String(t ?? '').match(/^(\d{1,2}):(\d{2})/);
    if (!m) return t ? String(t) : 'N/A';
    let h = +m[1]; const ap = h < 12 ? 'AM' : 'PM';
    h = h % 12 || 12;
    return h + ':' + m[2] + ' ' + ap;
  };
  // Stacked date-cell content for billing/treatment tables — the caller's own
  // already-formatted date HTML on top, the appointment's time_slot beneath it
  // in smaller muted text (blank when there's no slot to show).
  window.dateTimeStack = (dateHtml, slot) => {
    const t = slot ? window.fmtTime(slot) : '';
    return dateHtml + (t ? '<div class="mt-0.5 text-[11px] font-normal text-steel-400 dark:text-steel-500">' + window.esc(t) + '</div>' : '');
  };

  /* ---------- Live clock ---------- */
  function tickClock() {
    document.querySelectorAll('[data-clock]').forEach((el) => {
      el.textContent = new Date().toLocaleString('en-US', {
        weekday: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit',
      });
    });
  }
  setInterval(tickClock, 1000);
  tickClock();

  /* ---------- Dropdowns (bell + profile) ---------- */
  function wireDropdown(btnId, panelId, wrapId) {
    const btn = document.getElementById(btnId);
    const panel = document.getElementById(panelId);
    const wrap = document.getElementById(wrapId);
    if (!btn || !panel) return;
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const showing = !panel.hidden;
      document.querySelectorAll('.bell-panel, .profile-menu').forEach((p) => (p.hidden = true));
      panel.hidden = showing;
      if (!panel.hidden && btnId === 'bellBtn') loadNotifications();
      if (!panel.hidden && btnId === 'oncallBellBtn') loadOnCallBell();
      if (!panel.hidden && btnId === 'asstBellBtn') loadAsstBell();
      if (!panel.hidden && btnId === 'rcpBellBtn') loadRcpBell();
    });
    document.addEventListener('click', (e) => { if (wrap && !wrap.contains(e.target)) panel.hidden = true; });
  }

  async function loadNotifications() {
    const list = document.getElementById('bellList');
    if (!list) return;
    try {
      const { notifications } = await window.api('notifications.php?action=list');
      if (!notifications.length) { list.innerHTML = '<p class="p-3.5 text-steel-400">No notifications yet.</p>'; return; }
      // Clickable rows: each opens the pop-up (window.notifPopup) with the details.
      list.innerHTML = notifications.map((n) => window.notifRow(n)).join('');
    } catch (err) { list.innerHTML = '<p class="p-3.5 text-steel-400">' + esc(err.message) + '</p>'; }
  }

  async function refreshBellCount() {
    const badge = document.getElementById('bellCount');
    if (!badge) return;
    try {
      const { count } = await window.api('notifications.php?action=count');
      badge.textContent = count;
      badge.classList.toggle('hidden', count === 0);
    } catch { /* ignore */ }
  }

  document.addEventListener('click', async (e) => {
    if (e.target && e.target.id === 'markAllRead') {
      try {
        await window.api('notifications.php?action=read_all', { method: 'POST', body: {} });
        loadNotifications();
        refreshBellCount();
      } catch (err) { window.toast(err.message, 'err'); }
    }
  });

  wireDropdown('bellBtn', 'bellPanel', 'bellWrap');
  refreshBellCount();
  setInterval(refreshBellCount, 30000);
  window.refreshBellCount = refreshBellCount;

  /* ---------- Structured notification cards (migration 062) ----------
     One renderer for the three workflow notifications, shared by the header
     bells, the notification pages and the dentist / receptionist dashboards:
       oncall_assigned          roaming dentist: View Details / Decline
       oncall_declined          receptionist:    Reschedule / Reassign
       appointment_rescheduled  patient:         details only (also e-mailed)
     api/notifications.php joins each row to its appointment's LIVE details,
     so a card always shows the current schedule and drops its buttons once
     the situation is resolved (reassigned, cancelled, checked in...). */
  const NC_BRANCH = 'DGT One Dental Center - Bulacan';
  const NC_BTN = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold';
  const NC_GHOST = NC_BTN + ' border border-dgt-ink-200 bg-white text-dgt-blue-900 hover:bg-dgt-ink-50 dark:border-white/15 dark:bg-transparent dark:text-dgt-ink-100 dark:hover:bg-white/5';
  const NC_PRIMARY = NC_BTN + ' border border-transparent bg-dgt-teal-600 text-white hover:bg-dgt-teal-700';
  const NC_DANGER = NC_BTN + ' border border-danger/40 bg-white text-danger hover:bg-danger-soft dark:bg-transparent dark:hover:bg-danger/20';
  const NC_TONE = {
    oncall_assigned: 'bg-dgt-blue-50 text-dgt-blue-600 dark:bg-dgt-blue-500/20 dark:text-dgt-blue-200',
    oncall_declined: 'bg-danger-soft text-danger dark:bg-danger/20',
    appointment_rescheduled: 'bg-dgt-teal-50 text-dgt-teal-600 dark:bg-dgt-teal-500/20 dark:text-dgt-teal-200',
    assistant_assigned: 'bg-dgt-blue-50 text-dgt-blue-600 dark:bg-dgt-blue-500/20 dark:text-dgt-blue-200',
    schedule_conflict: 'bg-warn-soft text-warn dark:bg-warn/20',
    schedule_change: 'bg-warn-soft text-warn dark:bg-warn/20',
  };
  const NC_ICON = {
    // calendar-plus / alert-triangle / calendar-refresh, 24px grid, round caps
    oncall_assigned: '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4"/>',
    oncall_declined: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
    appointment_rescheduled: '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16.5a3 3 0 1 0 1-2.4M9 13v1.6h1.6"/>',
    // user-plus
    assistant_assigned: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
    // calendar-x (a dentist turned hours off)
    schedule_conflict: '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M10 14l4 4M14 14l-4 4"/>',
    schedule_change: '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M10 14l4 4M14 14l-4 4"/>',
  };
  const NC_GENERIC_ICON = '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>';

  const ncMe = () => +(document.body && document.body.dataset.userId) || 0;
  const ncWhen = (ts) => {
    const d = new Date(String(ts).replace(' ', 'T'));
    return isNaN(d) ? esc(ts) : d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  };
  const ncDate = (d) => {
    const x = new Date(String(d).slice(0, 10) + 'T00:00:00');
    return isNaN(x) ? esc(d) : x.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
  };
  const ncPast = (n) => !!n.scheduled_date && String(n.scheduled_date).slice(0, 10) < window.todayISO();

  // The newest card for an appointment is the only one that can still act;
  // an older duplicate (e.g. reassigned to the same dentist twice) is history.
  window.notifLatestIds = (list) => {
    const seen = new Set(); const keep = new Set();
    list.forEach((n) => {
      if (!n.appointment_id) { keep.add(+n.notification_id); return; }
      const k = n.type + ':' + n.appointment_id;
      if (!seen.has(k)) { seen.add(k); keep.add(+n.notification_id); }
    });
    return keep;
  };

  // Still-waiting notifications first (an assignment not yet declined, a
  // declined visit nobody has reassigned yet); handled ones drop below.
  // Newest first within each group (the API already returns newest first).
  // Status line for a handled one ('' while it still needs action).
  window.notifStatus = (n, isLatest) => {
    if (!NC_ICON[n.type]) return '';
    const st = ncState(n, isLatest);
    return st.act ? '' : (st.note || '');
  };
  window.notifSortOpenFirst = (list, latest) => list
    .map((n, i) => ({ n, i, open: ncState(n, latest.has(+n.notification_id)).act ? 1 : 0 }))
    .sort((a, b) => (b.open - a.open) || (a.i - b.i))
    .map((x) => x.n);

  // What a card can still do, plus an optional one-line status note.
  function ncState(n, isLatest) {
    const st = n.appt_status;
    const closed = st === 'cancelled' ? 'This appointment was cancelled.'
      : st === 'completed' ? 'This visit is completed.' : '';
    if (n.type === 'oncall_assigned') {
      const mine = +n.dentist_id === ncMe();
      if (!n.appointment_id || !st) return { view: false, act: false, note: 'This appointment is no longer available.' };
      if (closed) return { view: mine, act: false, note: closed };
      if (!mine) {
        return { view: false, act: false, note: +n.oncall_declined_by === ncMe()
          ? 'You declined this assignment' + (n.oncall_decline_reason ? ': ' + n.oncall_decline_reason : '') + '.'
          : 'This patient was reassigned to another dentist.' };
      }
      // A follow-up booking alerts the dentist before the front desk approves it.
      if (st === 'pending') return { view: true, act: false, note: 'Waiting for front desk approval.' };
      if (n.checked_in_at) return { view: true, act: false, note: 'The patient is checked in.' };
      if (ncPast(n) || !isLatest) return { view: true, act: false, note: '' };
      return { view: true, act: true, note: '' };
    }
    if (n.type === 'oncall_declined') {
      if (!n.appointment_id || !st) return { view: false, act: false, note: 'This appointment is no longer available.' };
      if (closed) return { view: true, act: false, note: closed };
      if (n.dentist_id) return { view: true, act: false, note: 'Resolved: now assigned to Dr. ' + (n.dentist_name || '') + '.' };
      return { view: true, act: isLatest, note: isLatest ? 'No dentist assigned yet.' : '' };
    }
    if (n.type === 'schedule_conflict') {
      // Receptionist: a dentist turned this hour off (migration 080).
      if (!n.appointment_id || !st) return { view: false, act: false, note: 'This appointment is no longer available.' };
      if (closed) return { view: true, act: false, note: closed };
      if (n.sched_change_status === 'needs_reschedule') return { view: true, act: isLatest, note: isLatest ? 'Needs a new time or another dentist.' : '' };
      if (n.sched_change_status === 'reassigned') return { view: true, act: false, note: 'Moved to Dr. ' + (n.dentist_name || '') + '.' };
      return { view: true, act: false, note: 'Resolved.' };
    }
    if (n.type === 'schedule_change') {
      if (!n.appointment_id || !st) return { view: false, act: false, note: '' };
      return { view: false, act: false, note: closed };
    }
    if (n.type === 'assistant_assigned') {
      if (!n.appointment_id || !st) return { view: false, act: false, note: 'This appointment is no longer available.' };
      if (closed) return { view: true, act: false, note: closed };
      return { view: true, act: false, note: '' };
    }
    return { view: false, act: false, note: '' };
  }

  function ncRows(pairs) {
    return '<dl class="mt-2 grid grid-cols-[92px_1fr] gap-x-3 gap-y-1 text-[12.5px] leading-snug">' +
      pairs.filter((p) => p[1] !== '' && p[1] != null).map((p) =>
        '<dt class="text-dgt-ink-500 dark:text-dgt-ink-400">' + esc(p[0]) + '</dt>' +
        '<dd class="min-w-0 break-words font-medium text-dgt-ink-700 dark:text-dgt-ink-100">' + p[1] + '</dd>').join('') +
      '</dl>';
  }

  /**
   * Render one notification as a card. opts:
   *   compact  tighter padding for the bell dropdown
   *   latest   Set of notification_ids allowed to show action buttons
   *   extra    (n) => extra button HTML (e.g. Mark read / Delete on a page)
   */
  // Details rows, status note and action buttons for one notification, shared
  // by the inline card (dashboards) and the pop-up (bell rows, pages).
  function ncParts(n, isLatest, big) {
    const typed = !!NC_ICON[n.type];
    const state = ncState(n, isLatest);
    const time = n.time_slot ? fmtTime(n.time_slot) : '';
    const sz = big ? ' !px-4 !py-2 !text-[13px]' : '';
    const NC_GHOST_ = NC_GHOST + sz, NC_PRIMARY_ = NC_PRIMARY + sz, NC_DANGER_ = NC_DANGER + sz;
    let body = '';
    let actions = '';
    if (n.type === 'oncall_assigned') {
      body = ncRows([
        ['Clinic Name', esc(NC_BRANCH)],
        ['Patient', esc(n.patient_name || '')],
        ['Date', n.scheduled_date ? ncDate(n.scheduled_date) : ''],
        ['Time', esc(time)],
        ['Service', esc(n.service_name || 'General Consultation')],
      ]);
      if (state.view) actions += '<button type="button" class="' + NC_GHOST_ + '" data-view="' + n.appointment_id + '">View Details</button>';
      if (state.act) actions += '<button type="button" class="' + NC_DANGER_ + '" data-oc-decline="' + n.appointment_id + '" data-name="' + esc(n.patient_name || '') + '">Decline</button>';
    } else if (n.type === 'oncall_declined') {
      body = ncRows([
        ['Dentist', esc(n.oncall_declined_by_name ? 'Dr. ' + n.oncall_declined_by_name : '')],
        ['Patient', esc(n.patient_name || '')],
        ['Reason', esc(n.oncall_decline_reason || '')],
        ['Date', n.scheduled_date ? ncDate(n.scheduled_date) : ''],
        ['Time', esc(time)],
        ['Service', esc(n.service_name || 'General Consultation')],
      ]);
      if (state.act) {
        actions += '<button type="button" class="' + NC_PRIMARY_ + '" data-rc-resched="' + n.appointment_id + '">Reschedule</button>' +
          '<button type="button" class="' + NC_GHOST_ + '" data-rc-reassign="' + n.appointment_id + '">Reassign</button>';
      }
      if (state.view) actions += '<button type="button" class="' + NC_GHOST_ + '" data-view="' + n.appointment_id + '">View</button>';
    } else if (n.type === 'schedule_conflict') {
      body = '<p class="mt-1 text-[12.5px] leading-snug text-dgt-ink-600 dark:text-dgt-ink-300">' + esc(n.message) + '</p>' + ncRows([
        ['Patient', esc(n.patient_name || '')],
        ['Date', n.scheduled_date ? ncDate(n.scheduled_date) : ''],
        ['Time', esc(time)],
        ['Service', esc(n.service_name || 'General Consultation')],
        ['Dentist', esc(n.dentist_name ? 'Dr. ' + n.dentist_name : '')],
      ]);
      if (state.act) {
        actions += '<button type="button" class="' + NC_GHOST_ + '" data-rc-reassign="' + n.appointment_id + '">Reassign</button>' +
          '<button type="button" class="' + NC_PRIMARY_ + '" data-rc-resched="' + n.appointment_id + '">Reschedule</button>';
      }
      if (state.view) actions += '<button type="button" class="' + NC_GHOST_ + '" data-view="' + n.appointment_id + '">View</button>';
    } else if (n.type === 'assistant_assigned') {
      body = ncRows([
        ['Patient', esc(n.patient_name || '')],
        ['Date', n.scheduled_date ? ncDate(n.scheduled_date) : ''],
        ['Time', esc(time)],
        ['Service', esc(n.service_name || 'General Consultation')],
        ['Dentist', esc(n.dentist_name ? 'Dr. ' + n.dentist_name : '')],
        ['Assigned', /automatically/i.test(n.message || '') ? 'Automatically (least busy assistant on duty)' : 'By the front desk'],
      ]);
      if (state.view) actions += '<button type="button" class="' + NC_PRIMARY_ + '" data-view="' + n.appointment_id + '">View Details</button>';
    } else if (n.type === 'appointment_rescheduled') {
      const old = (String(n.message).match(/Old schedule: (.+?)\. New schedule/) || [])[1] || '';
      body = ncRows([
        ['Clinic', esc(NC_BRANCH)],
        ['Service', esc(n.service_name || 'General Consultation')],
        ['Old schedule', old ? '<span class="text-dgt-ink-500 line-through dark:text-dgt-ink-400">' + esc(old) + '</span>' : ''],
        ['New schedule', n.scheduled_date ? ncDate(n.scheduled_date) + ' at ' + esc(time) : ''],
        ['Dentist', esc(n.dentist_name ? 'Dr. ' + n.dentist_name : 'To be confirmed')],
      ]);
    } else {
      body = '<p class="mt-1 text-[12.5px] leading-snug text-dgt-ink-600 dark:text-dgt-ink-300">' + esc(n.message) + '</p>';
    }
    if (document.body.dataset.role === 'patient' && n.appointment_id && n.appt_status) {
      actions += '<button type="button" class="' + NC_PRIMARY_ + '" data-view="' + n.appointment_id + '">View Appointment</button>';
    }
    const tone = NC_TONE[n.type] || 'bg-dgt-ink-100 text-dgt-ink-600 dark:bg-white/10 dark:text-dgt-ink-200';
    const note = typed && state.note
      ? '<p class="mt-2 text-[12px] font-medium ' + (state.act ? 'text-warn' : 'text-dgt-ink-500 dark:text-dgt-ink-400') + '">' + esc(state.note) + '</p>' : '';
    return { body, actions, tone, note };
  }

  function ncIcon(n, tone, size) {
    return '<span class="grid ' + (size || 'h-9 w-9') + ' shrink-0 place-items-center rounded-full ' + tone + '">' +
      '<svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      (NC_ICON[n.type] || NC_GENERIC_ICON) + '</svg></span>';
  }

  window.notifCard = (n, opts = {}) => {
    const isLatest = !opts.latest || opts.latest.has(+n.notification_id);
    const unread = n.is_read == 0;
    let { body, actions, tone, note } = ncParts(n, isLatest, false);
    if (opts.extra) actions += opts.extra(n);
    return '<article class="nc-card relative rounded-xl border ' +
      (unread ? 'border-dgt-blue-200 bg-dgt-blue-50/40 dark:border-dgt-blue-400/40 dark:bg-dgt-blue-500/10' : 'border-dgt-ink-200 bg-white dark:border-white/10 dark:bg-dgt-ink-800') +
      ' ' + (opts.compact ? 'p-3' : 'p-4') + ' text-left" data-nid="' + n.notification_id + '" data-unread="' + (unread ? 1 : 0) + '">' +
      '<div class="flex items-start gap-3">' + ncIcon(n, tone) +
      '<div class="min-w-0 flex-1">' +
      '<div class="flex items-start justify-between gap-2">' +
      '<h3 class="text-[13.5px] font-semibold leading-snug text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(n.title) +
      (unread ? '<span class="ml-1.5 inline-block h-2 w-2 rounded-full bg-dgt-blue-500 align-middle" aria-label="Unread"></span>' : '') + '</h3>' +
      '<time class="shrink-0 text-[11px] text-dgt-ink-400">' + ncWhen(n.created_at) + '</time></div>' +
      body + note +
      (actions ? '<div class="mt-3 flex flex-wrap gap-2">' + actions + '</div>' : '') +
      '</div></div></article>';
  };

  /* Plain list row, the same look as the patient bell (title, message, time).
     Clicking it opens notifPopup() with the details and the action buttons. */
  const ncCache = new Map();   // notification_id -> { n, isLatest }
  window.notifRow = (n, opts = {}) => {
    const isLatest = !opts.latest || opts.latest.has(+n.notification_id);
    ncCache.set(+n.notification_id, { n, isLatest });
    const st = NC_ICON[n.type] ? ncState(n, isLatest) : null;
    const status = st && !st.act && st.note ? ' · ' + esc(st.note) : '';
    const NOTIF = 'notif flex flex-col gap-0.5 border-b border-steel-100 dark:border-white/10 px-3.5 py-[11px] text-inherit no-underline hover:bg-steel-50 dark:hover:bg-white/10 cursor-pointer [&_b]:text-[13px] [&_span]:text-[13px] [&_small]:text-[11px] [&_small]:text-steel-400 dark:[&_small]:text-dgt-ink-400';
    return '<a href="#" class="' + NOTIF + (n.is_read == 0 ? ' bg-accent-soft dark:bg-accent/20' : '') + '" data-nc-open="' + n.notification_id + '">' +
      // Every role sees the title plus one short line; the full message is in the pop-up.
      '<b>' + esc(n.title) + '</b><span>' + esc(window.notifBrief(n)) + '</span>' +
      '<small>' + new Date(String(n.created_at).replace(' ', 'T')).toLocaleString() + status + '</small></a>';
  };
  // One short line for a patient row: just what matters (the schedule, the
  // service). The full message stays in the pop-up.
  window.notifBrief = (n) => {
    if (n.appointment_id && n.scheduled_date) {
      const d = new Date(String(n.scheduled_date).slice(0, 10) + 'T00:00:00');
      const when = (isNaN(d) ? String(n.scheduled_date) : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })) +
        (n.time_slot ? ' at ' + window.fmtTime(n.time_slot) : '');
      const parts = [];
      // Staff rows also name the patient (a patient already knows it's them).
      if (document.body.dataset.role !== 'patient' && n.patient_name) parts.push(n.patient_name);
      if (n.type === 'appointment_rescheduled') parts.push('New schedule: ' + when);
      else parts.push(when);
      if (n.service_name) parts.push(n.service_name);
      if (n.title === 'Dentist reassigned' && n.dentist_name) parts.push('Dr. ' + n.dentist_name);
      return parts.join(' · ');
    }
    // First sentence only. A period after a title (Dr., Mrs.) or an initial
    // (the "D.G." in a name) doesn't end the sentence.
    const msg = String(n.message || '');
    // Follow-up recommendation: just "Dr. X recommends a follow-up visit for <patient>".
    if (n.title === 'Recommended follow-up' && msg.indexOf(':') > 0) return msg.slice(0, msg.indexOf(':'));
    let first = msg;
    const re = /\.\s+/g;
    let m;
    while ((m = re.exec(msg))) {
      const word = (msg.slice(0, m.index).match(/(\S+)$/) || ['', ''])[1];
      if (/^(dr|dra|mr|mrs|ms|st|jr|sr)$/i.test(word) || /^([A-Za-z]\.)*[A-Za-z]$/.test(word)) continue;
      first = msg.slice(0, m.index + 1);
      break;
    }
    return first.length > 90 ? first.slice(0, 87) + '...' : first;
  };
  // Remember a row rendered somewhere else (e.g. a page table) for the pop-up.
  window.notifRemember = (n, isLatest) => ncCache.set(+n.notification_id, { n, isLatest: isLatest !== false });

  let ncLastPopup = 0;   // the notification whose pop-up is (or was last) showing
  window.notifPopup = (id) => {
    const hit = ncCache.get(+id);
    if (!hit) return;
    ncLastPopup = +id;
    const { n, isLatest } = hit;
    const { body, actions, tone, note } = ncParts(n, isLatest, true);
    window.openModal(window.modalShell(esc(n.title),
      '<div class="flex items-start gap-3">' + ncIcon(n, tone, 'h-10 w-10') +
      '<div class="min-w-0 flex-1"><p class="text-[12px] text-dgt-ink-400">Received ' + ncWhen(n.created_at) + '</p>' +
      body + note + '</div></div>',
      '<button class="' + NC_GHOST + ' !px-4 !py-2 !text-[13px]" data-close>Close</button>' + actions));
    if (n.is_read == 0) {
      n.is_read = 1;
      window.api('notifications.php?action=read_one', { method: 'POST', body: { notification_id: +n.notification_id } })
        .then(notifChanged).catch(() => {});
    }
  };

  // Pages that list notification cards set this so a Decline / Reschedule /
  // Reassign anywhere (bell or page) refreshes them. Overwritten per page run.
  window.__onNotifChanged = null;
  function notifChanged() {
    window.refreshOnCallBellCount && window.refreshOnCallBellCount();
    window.refreshRcpBellCount && window.refreshRcpBellCount();
    window.refreshBellCount && window.refreshBellCount();
    window.refreshAsstBellCount && window.refreshAsstBellCount();
    if (typeof window.__onNotifChanged === 'function') { try { window.__onNotifChanged(); } catch (_) { /* page gone */ } }
  }
  window.notifChanged = notifChanged;

  // Roaming dentist: decline an on-call assignment with a reason.
  const OC_REASONS = ['Schedule Overlap', 'Personal or Family Emergency', 'Not Feeling Well', 'Other'];
  window.oncallDeclineModal = (id, name) => {
    const LBL_ = 'mb-1.5 block text-xs font-semibold text-dgt-ink-600 dark:text-dgt-ink-300';
    const INP_ = 'w-full rounded-md border border-dgt-ink-200 bg-white px-3 py-2 text-[13px] text-dgt-ink-700 outline-none focus:border-dgt-blue-500 focus:ring-2 focus:ring-dgt-blue-100 dark:border-white/15 dark:bg-dgt-ink-900 dark:text-dgt-ink-100';
    window.openModal(window.modalShell('Decline On-Call Assignment',
      '<div class="flex flex-col gap-3.5">' +
      '<p class="text-[13px] text-dgt-ink-700 dark:text-dgt-ink-200">You are declining <strong>' + esc(name || 'this patient') + '</strong>. ' +
      'The front desk is alerted right away to reassign a dentist or reschedule the visit.</p>' +
      '<div><label class="' + LBL_ + '" for="ocd_reason">Reason</label><select class="' + INP_ + '" id="ocd_reason">' +
      OC_REASONS.map((r) => '<option>' + esc(r) + '</option>').join('') + '</select></div>' +
      '<div><label class="' + LBL_ + '" for="ocd_note">Details <span class="font-normal text-dgt-ink-400">(required for Other)</span></label>' +
      '<textarea class="' + INP_ + ' min-h-[70px] resize-y" id="ocd_note" maxlength="200" placeholder="e.g. I have a procedure at another branch at that time."></textarea></div>' +
      '</div>',
      '<button class="' + NC_GHOST + ' !px-4 !py-2 !text-[13px]" data-close>Keep Assignment</button>' +
      '<button class="' + NC_BTN + ' !px-4 !py-2 !text-[13px] border border-transparent bg-danger text-white hover:brightness-95" id="ocd_go">Decline Assignment</button>'),
      { stack: !!document.querySelector('#modalRoot:not([hidden]) .modal-pane') });
    document.getElementById('ocd_go').addEventListener('click', async (e) => {
      const sel = document.getElementById('ocd_reason').value;
      const note = document.getElementById('ocd_note').value.trim();
      if (sel === 'Other' && !note) return window.toast('Please describe the reason.', 'err');
      const reason = sel === 'Other' ? note : (note ? sel + ' (' + note + ')' : sel);
      e.currentTarget.disabled = true;
      try {
        await window.api('appointments.php?action=decline_oncall', { method: 'POST', body: { appointment_id: +id, reason } });
        window.closeModal();
        window.toast('Assignment declined. The front desk has been notified.');
        notifChanged();
      } catch (err) {
        window.toast(err.message, 'err');
        const b = document.getElementById('ocd_go'); if (b) b.disabled = false;
      }
    });
  };

  // Receptionist: Reschedule / Reassign live on the Appointments dashboard
  // (they reuse its calendar picker and duty-aware dentist list). From any
  // other page the request is handed over through sessionStorage, the same
  // hand-off the booking calendar uses.
  const RCP_DASH = '/dgt_dental_clinic/modules/receptionist/appointments';
  function rcpOpen(act, id, nid) {
    if (location.pathname === RCP_DASH && typeof window.__rcpOpen === 'function') { window.__rcpOpen(act, id, nid); return; }
    try { sessionStorage.setItem('dgt_rcp_action', JSON.stringify({ act, id, nid })); } catch (_) { /* private mode */ }
    const a = document.createElement('a');
    a.href = RCP_DASH; a.setAttribute('data-nav', ''); a.hidden = true;
    document.body.appendChild(a); a.click(); a.remove();
  }


  document.addEventListener('click', (e) => {
    const row = e.target.closest('[data-nc-open]');
    if (row) {
      e.preventDefault();
      document.querySelectorAll('.bell-panel').forEach((p) => (p.hidden = true));
      window.notifPopup(+row.dataset.ncOpen);
      return;
    }
    const card = e.target.closest('.nc-card');
    const dec = e.target.closest('[data-oc-decline]');
    const rs = e.target.closest('[data-rc-resched]');
    const ra = e.target.closest('[data-rc-reassign]');
    const anyAct = dec || rs || ra || e.target.closest('[data-view]');
    // Acting on an unread card marks it read.
    if (card && anyAct && card.dataset.unread === '1') {
      card.dataset.unread = '0';
      window.api('notifications.php?action=read_one', { method: 'POST', body: { notification_id: +card.dataset.nid } })
        .then(notifChanged).catch(() => {});
    }
    if (!dec && !rs && !ra) return;
    document.querySelectorAll('.bell-panel').forEach((p) => (p.hidden = true));
    if (dec) window.oncallDeclineModal(+dec.dataset.ocDecline, dec.dataset.name);
    // Opened from a notification: remember it so Cancel can go back to it.
    const nid = card ? +card.dataset.nid : (e.target.closest('#modalRoot') ? ncLastPopup : 0);
    if (rs) rcpOpen('reschedule', +rs.dataset.rcResched, nid);
    if (ra) rcpOpen('reassign', +ra.dataset.rcReassign, nid);
  });

  // Turns the open modal's footer Cancel / Close into "back to the
  // notification pop-up" (Reschedule / Reassign opened from a notification).
  window.notifReturnOnCancel = (nid) => {
    if (!nid || !ncCache.has(+nid)) return;
    // The newest pane (while a slide runs, the pop-up's pane is still mounted too).
    const feet = document.querySelectorAll('#modalRoot .modal-pane .modal-foot');
    const foot = feet[feet.length - 1];
    const b = foot && foot.querySelector('[data-close]');
    if (!b) return;
    // No X here: Back / Cancel return to the pop-up instead of closing everything.
    const x = foot.closest('.modal-pane').querySelector('.modal-close');
    if (x) { x.style.visibility = 'hidden'; x.setAttribute('aria-hidden', 'true'); }   // hidden, not removed: keeps the title centred
    // A clone drops the closeModal listener openModal() put on the original.
    const back = b.cloneNode(true);
    back.removeAttribute('data-close');
    // Stacked on the pop-up: slide back to it, like the Back button.
    back.addEventListener('click', (e) => {
      e.preventDefault();
      if (modalStack.length) window.modalBack(); else window.notifPopup(+nid);
    });
    b.replaceWith(back);
  };

  /* ---------- Roaming-dentist on-call bell (2026-09) ----------
     A narrow, deliberate companion to the patient bell above — separate ids,
     own dropdown, scoped to just the 'New On-Call Assignment' notification
     title (same scoping the dashboard's own on-call card uses) so it stays
     "incoming on-call assignments" even if some other notify() is ever aimed
     at a dentist later. Every function below is a safe no-op when the button
     isn't in the DOM (every role but a roaming dentist). */
  const ONCALL_TITLE = 'New On-Call Assignment';
  const isOnCall = (n) => n.type === 'oncall_assigned' || n.title === ONCALL_TITLE;

  async function loadOnCallBell() {
    const list = document.getElementById('oncallBellList');
    if (!list) return;
    try {
      const { notifications } = await window.api('notifications.php?action=list');
      const onCall = notifications.filter(isOnCall);
      if (!onCall.length) { list.innerHTML = '<p class="p-3.5 text-steel-400">No on-call assignments yet.</p>'; return; }
      const latest = window.notifLatestIds(onCall);
      list.innerHTML = window.notifSortOpenFirst(onCall, latest).map((n) => window.notifRow(n, { latest })).join('');
    } catch (err) { list.innerHTML = '<p class="p-3.5 text-steel-400">' + esc(err.message) + '</p>'; }
  }

  async function refreshOnCallBellCount() {
    const badge = document.getElementById('oncallBellCount');
    if (!badge) return;
    try {
      const { notifications } = await window.api('notifications.php?action=list');
      const count = notifications.filter((n) => isOnCall(n) && n.is_read == 0).length;
      badge.textContent = count;
      badge.classList.toggle('hidden', count === 0);
    } catch { /* ignore */ }
  }

  function scrollToOnCallCard() {
    // Simplified 2026-09 — there's no separate "On-Call Assignments" card any
    // more; an approved overflow booking is just an ordinary row in the
    // dentist's own Queue table, so that's what the bell scrolls to.
    const el = document.getElementById('queueSection');
    if (!el) return;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    el.classList.add('oc-flash');
    setTimeout(() => el.classList.remove('oc-flash'), 1500);
  }

  document.addEventListener('click', async (e) => {
    if (e.target && e.target.id === 'oncallMarkAllRead') {
      try {
        await window.api('notifications.php?action=read_all', { method: 'POST', body: {} });
        loadOnCallBell();
        refreshOnCallBellCount();
      } catch (err) { window.toast(err.message, 'err'); }
      return;
    }
  });

  wireDropdown('oncallBellBtn', 'oncallBellPanel', 'oncallBellWrap');
  if (document.getElementById('oncallBellBtn')) {
    refreshOnCallBellCount();
    setInterval(refreshOnCallBellCount, 30000);
  }
  window.refreshOnCallBellCount = refreshOnCallBellCount;
  window.loadOnCallBell = loadOnCallBell;

  /* ---------- Dental-assistant "New Patient Assigned" bell (2026-09) ----------
     A third, equally narrow companion to the two bells above — separate ids,
     own dropdown, scoped to just the 'New Patient Assigned' notification
     title (written by api/appointments.php's check_in action when the front
     desk leaves the Assisting Staff picker blank and the least-loaded
     on-duty assistant is auto-assigned instead). Every function below is a
     safe no-op when the button isn't in the DOM (every role but a dental
     assistant). */
  const ASST_TITLE = 'New Patient Assigned';

  async function loadAsstBell() {
    const list = document.getElementById('asstBellList');
    if (!list) return;
    try {
      const { notifications } = await window.api('notifications.php?action=list');
      const mine = notifications.filter((n) => n.title === ASST_TITLE);
      if (!mine.length) { list.innerHTML = '<p class="p-3.5 text-steel-400">No assignments yet.</p>'; return; }
      // Same rows as the other bells: a click opens the details pop-up.
      list.innerHTML = mine.map((n) => window.notifRow(n)).join('');
    } catch (err) { list.innerHTML = '<p class="p-3.5 text-steel-400">' + esc(err.message) + '</p>'; }
  }

  // Highest notification_id already seen this page load — null until the
  // first poll establishes a baseline, so a brand-new page load never
  // toasts for assignments that already existed before it opened (only a
  // genuinely new one arriving *during* the session does). Bounded by the
  // 30s poll below — this app has no websocket/push layer, so "real-time" is
  // "as fresh as the next poll", the same near-live convention every other
  // auto-refreshing surface in this app already uses (dashboard cards, the
  // on-call bell's own count).
  let asstSeenMaxId = null;

  async function refreshAsstBellCount() {
    const badge = document.getElementById('asstBellCount');
    if (!badge) return;
    try {
      const { notifications } = await window.api('notifications.php?action=list');
      const mine = notifications.filter((n) => n.title === ASST_TITLE);
      const count = mine.filter((n) => n.is_read == 0).length;
      badge.textContent = count;
      badge.classList.toggle('hidden', count === 0);

      const maxId = mine.reduce((m, n) => Math.max(m, +n.notification_id), 0);
      if (asstSeenMaxId === null) {
        asstSeenMaxId = maxId;
      } else if (maxId > asstSeenMaxId) {
        const fresh = mine.filter((n) => +n.notification_id > asstSeenMaxId);
        asstSeenMaxId = maxId;
        // Oldest-first, so a rapid run of check-ins toasts in the order they
        // actually happened rather than newest-first.
        fresh.slice().reverse().forEach((n) => window.toast(n.message, 'info'));
      }
    } catch { /* ignore */ }
  }

  function scrollToAsstSchedule() {
    const el = document.getElementById('schedSection');
    if (!el) return;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
    el.classList.add('asst-flash');
    setTimeout(() => el.classList.remove('asst-flash'), 1500);
  }

  document.addEventListener('click', async (e) => {
    if (e.target && e.target.id === 'asstMarkAllRead') {
      try {
        await window.api('notifications.php?action=read_all', { method: 'POST', body: {} });
        loadAsstBell();
        refreshAsstBellCount();
      } catch (err) { window.toast(err.message, 'err'); }
      return;
    }
  });

  wireDropdown('asstBellBtn', 'asstBellPanel', 'asstBellWrap');
  if (document.getElementById('asstBellBtn')) {
    refreshAsstBellCount();
    setInterval(refreshAsstBellCount, 30000);
  }


  /* ---------- Receptionist "On-Call Assignment Declined" bell (migration 062) ----------
     A fourth narrow exception to the patient-only bell: the front desk is
     alerted the moment a roaming dentist declines an assignment, with
     Reschedule / Reassign right on the card. Scoped to type oncall_declined.
     Safe no-op for every other role (the button isn't rendered). */
  const isRcpAlert = (n) => n.type === 'oncall_declined' || n.type === 'schedule_conflict';
  async function loadRcpBell() {
    const list = document.getElementById('rcpBellList');
    if (!list) return;
    try {
      const { notifications } = await window.api('notifications.php?action=list');
      const mine = notifications.filter(isRcpAlert);
      if (!mine.length) { list.innerHTML = '<p class="p-3.5 text-steel-400">No alerts right now.</p>'; return; }
      const latest = window.notifLatestIds(mine);
      list.innerHTML = window.notifSortOpenFirst(mine, latest).map((n) => window.notifRow(n, { latest })).join('');
    } catch (err) { list.innerHTML = '<p class="p-3.5 text-steel-400">' + esc(err.message) + '</p>'; }
  }
  let rcpSeenMaxId = null;
  async function refreshRcpBellCount() {
    const badge = document.getElementById('rcpBellCount');
    if (!badge) return;
    try {
      const { notifications } = await window.api('notifications.php?action=list');
      const mine = notifications.filter(isRcpAlert);
      const count = mine.filter((n) => n.is_read == 0).length;
      badge.textContent = count;
      badge.classList.toggle('hidden', count === 0);
      const panel = document.getElementById('rcpBellPanel');
      if (panel && !panel.hidden) loadRcpBell();
      // Same near-real-time toast as the assistant bell: only alerts that
      // arrive while the page is open, never the backlog on first load.
      const maxId = mine.reduce((m, n) => Math.max(m, +n.notification_id), 0);
      if (rcpSeenMaxId === null) rcpSeenMaxId = maxId;
      else if (maxId > rcpSeenMaxId) {
        mine.filter((n) => +n.notification_id > rcpSeenMaxId).reverse()
          .forEach((n) => window.toast(n.title + ': ' + (n.patient_name || '') + (n.type === 'schedule_conflict' ? '. See the Action Needed list.' : '. Please reassign or reschedule.'), 'err'));
        rcpSeenMaxId = maxId;
        if (typeof window.__onNotifChanged === 'function') { try { window.__onNotifChanged(); } catch (_) { /* page gone */ } }
      }
    } catch { /* ignore */ }
  }
  document.addEventListener('click', async (e) => {
    if (e.target && e.target.id === 'rcpMarkAllRead') {
      try {
        await window.api('notifications.php?action=read_all', { method: 'POST', body: {} });
        loadRcpBell(); refreshRcpBellCount();
      } catch (err) { window.toast(err.message, 'err'); }
    }
  });
  wireDropdown('rcpBellBtn', 'rcpBellPanel', 'rcpBellWrap');
  if (document.getElementById('rcpBellBtn')) {
    refreshRcpBellCount();
    setInterval(refreshRcpBellCount, 30000);
  }
  window.refreshRcpBellCount = refreshRcpBellCount;

  /* ---------- Shared appointment drill-down ---------- */
  window.badge = (s) => window.badgeHTML(String(s).replace('_', ' '), s);

  // How the appointment was booked — the "Scheduled" column in the dentist views.
  window.srcBadge = (t) => {
    const walk = String(t) === 'walk_in';
    return window.badgeHTML(walk ? 'Walk-In' : 'Online', walk ? 'src-walkin' : 'src-online');
  };

  // There is no "Ongoing" status any more (removed 2026-09). Every appointment
  // table + modal renders the plain appointment status — Approved while the
  // visit is in progress, Completed once the dentist finishes. Check-in is still
  // tracked (checked_in_at) and still gates "Perform Procedure", but it no longer
  // changes the badge. Both helpers are kept as thin aliases so existing callers
  // don't need to change.
  // Grace period (migration 088): an approved visit not checked in 15 minutes
  // after its start shows Delayed / Standby (its seat went to walk-ins); one
  // checked in after that shows Arrived (Late). Otherwise the plain status.
  const lateBadge = (row) => {
    if (!row || typeof row !== 'object' || !row.delayed_at || !['approved', 'rescheduled'].includes(row.status)) return '';
    return row.checked_in_at ? window.badgeHTML('Arrived (Late)', 'arrived_late') : window.badgeHTML('Delayed / Standby', 'delayed');
  };
  window.apptStatusBadge = (row) =>
    lateBadge(row) || window.badge(String((row && typeof row === 'object' ? row.status : row) || ''));

  window.opStatus = window.apptStatusBadge;

  // The patient's booking concern = everything in appointments.notes before the
  // "Treatment notes:" marker the backend appends when a procedure is logged.
  window.initialConcern = (raw) => String(raw || '').split(/treatment notes:/i)[0].trim();

  // Billing-status badge from the payment row. "Sent to Payment" until real money
  // (cash/GCash/bank — not the reservation credit) is collected; "Partial
  // Payment" while a balance remains; "Paid" at zero balance.
  window.billingBadge = (p) => {
    const bal = parseFloat(p.remaining_balance) || 0;
    const procPaid = (parseFloat(p.amount_cash) || 0) + (parseFloat(p.amount_gcash) || 0) + (parseFloat(p.amount_bank) || 0);
    if (bal <= 0.005 && (p.payment_status === 'fully_paid' || procPaid + (parseFloat(p.reservation_fee_paid) || 0) > 0)) {
      return { cls: 'billing-paid', label: 'Paid' };
    }
    if (procPaid > 0) return { cls: 'billing-pending', label: 'Partial Payment' };
    return { cls: 'billing-pending', label: 'Sent to Payment' };
  };

  // Comma-joined service list for a table cell: show up to 2, then "+N more".
  window.serviceSummary = (raw) => {
    const list = String(raw || '').split(',').map((x) => x.trim()).filter(Boolean);
    if (!list.length) return '<span class="text-steel-400">N/A</span>';
    if (list.length <= 2) return esc(list.join(', '));
    return esc(list.slice(0, 2).join(', ')) + ' <span class="text-steel-400">+' + (list.length - 2) + ' more</span>';
  };

  // Shared "Services Availed" logger, used by the dentist Queue and the calendar
  // day view. `services` is the active catalogue; `onDone()` runs after a
  // successful submit. Callers only show the trigger for today/past visits that
  // are still scheduled/rescheduled — api/treatments.php re-checks server-side.
  // Plural of a services.quantity_unit: tooth -> teeth, tooth surface -> tooth surfaces.
  window.unitPlural = (u) => (u === 'tooth' ? 'teeth' : String(u || '') + 's');

  // Tooth drawings (2026-10-18), shared by the dentist Patient Details chart and
  // the Services Availed tooth picker. Upper teeth point their roots up, lower
  // teeth down; a status is drawn on the tooth (blue filling, green root line,
  // dashed outline + red X). CSS: the .tooth-svg rules in includes/tailwind.php.
  const TOOTH_SHAPE = {
    incisor: 'M12 5 Q20 2 28 5 L27 24 Q26 32 23 51 Q20 55 17 51 Q14 32 13 24 Z',
    canine: 'M20 2 L29 10 L27 24 Q25 34 22 52 Q20 55 18 52 Q15 34 13 24 L11 10 Z',
    premolar: 'M9 7 Q14 2 20 5 Q26 2 31 7 L30 24 Q28 34 24 51 Q20 55 16 51 Q12 34 10 24 Z',
    molar: 'M5 8 Q10 2 15 5 Q20 2 25 5 Q30 2 35 8 L34 24 Q33 36 30 51 Q27 55 24 50 L21 33 Q20 31 19 33 L16 50 Q13 55 10 51 Q7 36 6 24 Z',
  };
  const ORDINAL = ['', 'central', 'side', '', 'first', 'second', 'first', 'second', 'third'];
  window.toothInfo = function (t) {
    const q = Math.floor(t / 10), n = t % 10, baby = q >= 5;
    const type = n <= 2 ? 'incisor' : n === 3 ? 'canine' : baby ? 'molar' : n <= 5 ? 'premolar' : 'molar';
    const upper = [1, 2, 5, 6].includes(q), right = [1, 4, 5, 8].includes(q);
    let name = type === 'incisor' ? ORDINAL[n] + ' front tooth' : type === 'canine' ? 'canine (eye tooth)'
      : type === 'premolar' ? ORDINAL[n] + ' premolar'
      : baby ? (n === 4 ? 'first' : 'second') + ' baby molar'
      : n === 8 ? 'wisdom tooth (third molar)' : ORDINAL[n] + ' molar';
    return { type, upper, label: (baby ? 'Baby ' : '') + (upper ? 'upper ' : 'lower ') + (right ? 'right ' : 'left ') + name };
  };
  window.toothSvg = function (t, st) {
    const { type, upper } = window.toothInfo(t);
    const d = TOOTH_SHAPE[type];
    let extra = '';
    if (st === 'resto') extra = '<ellipse class="tf" cx="20" cy="12" rx="' + (type === 'molar' ? 8 : 5) + '" ry="4.5"/>';
    if (st === 'rct') extra = type === 'molar'
      ? '<path class="tr" d="M20 14 L13 46 M20 14 L27 46"/>' : '<path class="tr" d="M20 12 L20 48"/>';
    if (st === 'missing') extra = '<path class="tx" d="M8 14 L32 46 M32 14 L8 46"/>';
    // Decayed: a dark spot on the crown. For extraction: an arrow out of the socket.
    if (st === 'caries') extra = '<path class="tc" d="M15 9 Q19 6 24 9 Q26 15 20 17 Q14 16 15 9 Z"/>';
    if (st === 'extract') extra = '<path class="te" d="M20 26 L20 7 M14.5 12.5 L20 7 L25.5 12.5"/>';
    return '<svg class="tooth-svg" viewBox="0 0 40 56" aria-hidden="true"><g' + (upper ? ' transform="translate(0 56) scale(1 -1)"' : '') + '>' +
      '<path class="tb" d="' + d + '"/>' + extra + '</g></svg>';
  };

  /* ---------- Dental charting (2026-10-26, migration 091) ----------
     Tooth conditions + quick tags + a note per tooth per visit. Same lists as
     TOOTH_STATES / TOOTH_TAGS in api/_bootstrap.php. Used by the dentist
     Patient Details chart and the chart step before Perform Procedure. */
  window.TOOTH_STATUS = [
    { key: '', label: 'Healthy / Normal', tag: '', legend: 'Healthy / Normal: no problem found' },
    { key: 'caries', label: 'Decayed / Caries', tag: 'D', legend: 'Decayed / Caries (D): cavity found' },
    { key: 'extract', label: 'For Extraction', tag: 'FX', legend: 'For Extraction (FX): to be removed / pulled at this clinic' },
    { key: 'missing', label: 'Missing / Empty Slot', tag: 'X', legend: 'Missing (X): no tooth when first seen' },
    { key: 'resto', label: 'Restored / Filled / Pasta', tag: 'R', legend: 'Restored / Pasta (R): cavity filled' },
    { key: 'rct', label: 'Root Canal Treated', tag: 'RCT', legend: 'Root Canal (RCT): nerve treated' },
  ];
  const TS_BY = Object.fromEntries(window.TOOTH_STATUS.map((s) => [s.key, s]));
  window.toothStatusLabel = (k) => (TS_BY[k || ''] || TS_BY['']).label;
  // Quick tags in 3 groups (2026-10-26); same list as TOOTH_TAGS in api/_bootstrap.php.
  window.TOOTH_TAG_GROUPS = [
    ['Symptoms', ['Sensitive to cold', 'Sensitive to sweets', 'Pain when biting', 'Pain at night', 'Gum swelling', 'Bleeding gums']],
    ['Findings', ['Deep cavity', 'Fractured / chipped', 'Loose / mobile', 'Plaque / tartar', 'Stain / discoloration', 'Gum recession', 'Abscess / pus', 'Old filling worn', 'Temporary filling', 'Root stump', 'Impacted', 'Wisdom tooth erupting', 'Bracket loose', 'Wire poking']],
    ['Recommended', ['Needs cleaning', 'Needs filling (Resto)', 'Needs root canal', 'Needs extraction', 'Needs X-ray', 'Needs bridge', 'Needs denture', 'Needs veneer', 'Needs whitening', 'Needs gum treatment', 'Needs surgery', 'Braces adjustment', 'Monitor next visit']],
  ];
  window.TOOTH_TAGS = window.TOOTH_TAG_GROUPS.flatMap((g) => g[1]);
  const TOOTH_ROWS = [
    [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28]],
    [[48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38]],
    [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65]],
    [[85, 84, 83, 82, 81], [71, 72, 73, 74, 75]],
  ];
  const TOOTH_BAND = { adult: [['Molars', 3], ['Premolars', 2], ['Canine', 1], ['Front', 2]], baby: [['Molars', 2], ['Canine', 1], ['Front', 2]] };
  // Latest record per tooth (records come newest first from the API).
  window.toothLatest = function (records) {
    const m = {};
    (records || []).forEach((r) => { if (!m[r.tooth]) m[r.tooth] = r; });
    return m;
  };
  window.toothTitle = function (t, st, info) {
    const bits = info ? [].concat(info.tags || [], info.note ? [info.note] : []) : [];
    return 'Tooth ' + t + ': ' + window.toothInfo(t).label + ' · ' + window.toothStatusLabel(st) + (bits.length ? ' · ' + bits.join(', ') : '');
  };
  // The FDI board. opts: { clickable, baby, info: {tooth: {tags, note}}, pending: {tooth: entry}, hint, sides }
  window.toothBoardHtml = function (chart, opts) {
    const o = opts || {}, esc = window.esc;
    const sides = o.sides || ['&larr; Patient&rsquo;s right', 'Patient&rsquo;s left &rarr;'];
    const cell = (t) => {
      const st = chart[t] || '', inf = (o.info || {})[t];
      const dot = inf && ((inf.tags || []).length || inf.note) ? '<span class="tooth-note-dot" aria-hidden="true"></span>' : '';
      const ttl = esc(window.toothTitle(t, st, inf));
      return '<div class="tooth-cell"><span class="tooth-num">' + t + '</span>' +
        '<button type="button" class="tooth-box' + (st ? ' t-' + st : '') + ((o.pending || {})[t] ? ' t-pending' : '') + '" data-tooth="' + t + '" title="' + ttl + '" aria-label="' + ttl + '"' +
        (o.clickable ? '' : ' disabled') + '>' + dot + window.toothSvg(t, st) + '<span class="tooth-tag">' + ((TS_BY[st] || {}).tag || '') + '</span></button></div>';
    };
    const band = (kind) => {
      const groups = TOOTH_BAND[kind], n = groups.reduce((a, g) => a + g[1], 0);
      const half = (list) => '<div class="tooth-quad tooth-band" style="grid-template-columns:repeat(' + n + ',minmax(0,1fr))">' +
        list.map(([lbl, span]) => '<span style="grid-column:span ' + span + '">' + lbl + '</span>').join('') + '</div>';
      return '<div class="tooth-quad-row tooth-band-row"><span class="tooth-jaw"></span>' + half(groups) + half(groups.slice().reverse()) + '</div>';
    };
    const jaw = (label, [l, r]) => '<div class="tooth-quad-row"><span class="tooth-jaw">' + label + '</span>' +
      [l, r].map((q) => '<div class="tooth-quad">' + q.map(cell).join('') + '</div>').join('') + '</div>';
    const sec = (title, kind, up, low) => '<div class="tooth-sec' + (kind === 'baby' ? ' is-primary' : '') + '">' +
      '<div class="tooth-sec-head"><span class="tooth-sec-title">' + title + '</span>' +
      '<span class="tooth-sides"><span>' + sides[0] + '</span><span>' + sides[1] + '</span></span></div>' +
      jaw('Upper', up) + band(kind) + jaw('Lower', low) + '</div>';
    return '<div class="tooth-board">' + (o.hint ? '<p class="tooth-hint">' + o.hint + '</p>' : '') +
      sec('Adult teeth', 'adult', TOOTH_ROWS[0], TOOTH_ROWS[1]) +
      (o.baby ? sec('Baby teeth (children)', 'baby', TOOTH_ROWS[2], TOOTH_ROWS[3]) : '') + '</div>';
  };
  window.toothLegendHtml = function () {
    return '<div class="tooth-status-legend"><strong>Tooth Status:</strong>' +
      window.TOOTH_STATUS.map((s) => '<span' + (s.key ? ' class="t-' + s.key + '"' : '') + '>' + window.toothSvg(36, s.key) + ' ' + s.legend + '</span>').join('') +
      '<span><span class="tooth-note-dot"></span> Has tags or a note</span></div>';
  };
  // Baby teeth only for children: age 12 or younger, no birthdate, or a baby tooth marked.
  window.toothWantsBaby = function (dob, chart, records) {
    if (!dob) return true;
    const d = new Date(String(dob).slice(0, 10) + 'T00:00:00'), n = new Date();
    let age = n.getFullYear() - d.getFullYear();
    if (n.getMonth() < d.getMonth() || (n.getMonth() === d.getMonth() && n.getDate() < d.getDate())) age--;
    if (isNaN(age) || age <= 12) return true;
    return Object.keys(chart || {}).some((t) => +t >= 51 && chart[t]) || (records || []).some((r) => r.tooth >= 51);
  };
  // Records as a small list (one tooth's history, or every tooth with showTooth).
  window.toothHistoryHtml = function (records, showTooth) {
    const esc = window.esc;
    if (!records.length) return '<p class="m-0 text-[12.5px] text-steel-400">' + (showTooth ? 'No tooth records yet.' : 'No earlier records for this tooth.') + '</p>';
    return '<ul class="m-0 flex list-none flex-col gap-2 p-0">' + records.map((r) =>
      '<li class="rounded-lg border border-steel-100 px-3 py-2 text-[12.5px] dark:border-white/10">' +
        '<div class="flex flex-wrap items-center gap-x-2 text-[11.5px] text-steel-500 dark:text-steel-400"><b class="text-navy-800 dark:text-steel-100">' + esc(window.fmtDate(String(r.visit_date).slice(0, 10))) + '</b>' +
        (r.by_name ? '<span>' + (r.by_role === 'dentist' ? 'Dr. ' : '') + esc(r.by_name) + '</span>' : '') + '</div>' +
        '<div class="mt-0.5 font-semibold text-navy-900 dark:text-steel-50">' + (showTooth ? 'Tooth ' + r.tooth + ': ' : '') + esc(window.toothStatusLabel(r.status)) + '</div>' +
        ((r.tags || []).length ? '<div class="mt-1 flex flex-wrap gap-1">' + r.tags.map((t) => '<span class="rounded-full bg-steel-100 px-2 py-0.5 text-[11px] text-navy-700 dark:bg-white/10 dark:text-steel-200">' + esc(t) + '</span>').join('') + '</div>' : '') +
        (r.note ? '<div class="mt-1 text-navy-700 dark:text-steel-200">' + esc(r.note) + '</div>' : '') +
      '</li>').join('') + '</ul>';
  };

  // Tooth pop-up, above any open modal.
  // o: { tooth, status, tags, note, history, readOnly, lockStatus, onApply(entry) }
  // lockStatus: the condition is set by the service picked (Services Availed),
  // so the other condition buttons are greyed out; tags and note stay editable.
  window.toothDetailPopup = function (o) {
    const esc = window.esc, t = +o.tooth;
    let st = o.status || '';
    // picked: a condition button is shown as chosen. A tooth with no condition
    // opens with nothing chosen; Healthy is only highlighted once clicked.
    let picked = !!st;
    const tags = new Set(o.tags || []);
    // Missing / Resto / Root Canal set this visit (o.askExtract): done today,
    // or already like that before (the dentist recording what they found while
    // checking)? Only work done today goes on that service's row.
    let ex = typeof o.extracted === 'boolean' ? o.extracted : null;
    const EX_Q = {
      extract: ['Was this tooth pulled today?', 'Pulled this visit (Bunot)', 'Adds this tooth to the Extraction service.', 'Not yet, extract later'],
      resto: ['Was this filling done today?', 'Filled this visit (Resto)', 'Adds this tooth to the Resto service.', 'Already filled before this visit'],
      rct: ['Was this root canal done today?', 'Root canal done this visit', 'Adds this tooth to the Root Canal service.', 'Already root canal treated before this visit'],
    };
    // For Extraction asks even when the tooth was already planned for extraction
    // at an earlier visit (it may be pulled now); the others only on a change.
    const needEx = () => !!o.askExtract && !!EX_Q[st] && (st === 'extract' || st !== (o.baseStatus || ''));
    const EXBTN = 'tab outline-none flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2 text-left text-[12.5px]';
    const exBtns = () => { const q = EX_Q[st] || EX_Q.extract; return [[true, q[1], q[2]],
      [false, q[3], st === 'extract' ? 'Recorded as For Extraction only, not billed.' : 'Recorded on the chart only, not billed.']].map(([v, l, d]) =>
      '<button type="button" data-ex="' + v + '" aria-pressed="' + (ex === v) + '" class="' + EXBTN + ' ' +
        (ex === v ? 'border-accent bg-accent/10 text-navy-900 ring-2 ring-accent/30 dark:text-steel-50' : 'border-steel-200 bg-white text-navy-700 hover:border-accent dark:border-white/15 dark:bg-transparent dark:text-steel-100') + '">' +
        '<span class="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 ' + (ex === v ? 'border-accent' : 'border-steel-300') + '">' + (ex === v ? '<span class="h-2 w-2 rounded-full bg-accent"></span>' : '') + '</span>' +
        '<span><b class="block font-semibold">' + l + '</b><span class="text-[11.5px] text-steel-500 dark:text-steel-400">' + d + '</span></span></button>').join(''); };
    const syncEx = () => {
      const w = el.querySelector('#tpExWrap');
      if (!w) return;
      w.hidden = !needEx();
      el.querySelector('#tpExQ').textContent = (EX_Q[st] || EX_Q.extract)[0];
      el.querySelector('#tpEx').innerHTML = exBtns();
    };
    const STBTN = 'tab outline-none flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-2 text-left text-[12.5px] font-semibold disabled:cursor-default';
    const stBtns = () => window.TOOTH_STATUS.map((s) =>
      '<button type="button" data-st="' + s.key + '" aria-pressed="' + (picked && s.key === st) + '" class="' + STBTN + ' ' +
        (picked && s.key === st ? 'border-accent bg-accent/10 text-navy-900 ring-2 ring-accent/30 dark:text-steel-50'
          : o.lockStatus ? 'border-steel-200 bg-steel-50 text-steel-400 opacity-50 dark:border-white/10 dark:bg-white/5 dark:text-steel-500'
          : 'border-steel-200 bg-white text-navy-700 hover:border-accent dark:border-white/15 dark:bg-transparent dark:text-steel-100') + '"' +
        (o.readOnly || o.lockStatus ? ' disabled' : '') + '>' +
        '<span class="tp-ico inline-flex h-[30px] w-[22px] shrink-0 items-center justify-center' + (s.key ? ' t-' + s.key : '') + '">' + window.toothSvg(t, s.key) + '</span>' + esc(s.label) + '</button>').join('');
    const tagBtn = (g) =>
      '<button type="button" data-tag="' + esc(g) + '" aria-pressed="' + tags.has(g) + '" class="tab outline-none cursor-pointer rounded-full border px-2.5 py-1 text-[12px] font-medium disabled:cursor-default ' +
        (tags.has(g) ? 'border-[#6d28d9] bg-[#ede9fe] text-[#5b21b6] dark:border-[#a78bfa] dark:bg-[#a78bfa]/20 dark:text-[#ddd6fe]' : 'border-steel-200 bg-white text-navy-700 hover:border-[#6d28d9] dark:border-white/15 dark:bg-transparent dark:text-steel-100') + '"' +
        (o.readOnly ? ' disabled' : '') + '>' + (tags.has(g) ? '&#10003; ' : '+ ') + esc(g) + '</button>';
    const tagBtns = () => window.TOOTH_TAG_GROUPS.map(([name, list]) =>
      '<div class="mb-2 last:mb-0"><div class="mb-1 text-[11px] font-semibold text-steel-400 dark:text-steel-500">' + esc(name) + '</div>' +
      '<div class="flex flex-wrap gap-1.5">' + list.map(tagBtn).join('') + '</div></div>').join('');
    const el = document.createElement('div');
    el.className = 'tooth-pop fixed inset-0 z-[140] flex items-center justify-center bg-[rgba(15,28,46,.55)] p-4';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Tooth ' + t);
    const H = 'mb-1.5 text-[12px] font-bold uppercase tracking-wide text-steel-500 dark:text-steel-400';
    el.innerHTML =
      '<div class="flex max-h-[calc(100vh-32px)] w-full max-w-[640px] flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-dgt-ink-800">' +
        '<div class="flex items-center justify-between gap-3 border-b border-dgt-ink-200 px-5 py-3.5 dark:border-white/10">' +
          '<div><h3 class="m-0 text-[16px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">Tooth ' + t + '</h3>' +
          '<p class="m-0 text-[12px] capitalize text-steel-500 dark:text-steel-400">' + esc(window.toothInfo(t).label) + '</p></div>' +
          '<button type="button" class="modal-close inline-flex h-9 w-9 items-center justify-center rounded-full text-[22px] leading-none text-dgt-ink-500 hover:bg-dgt-ink-100 dark:text-dgt-ink-300 dark:hover:bg-white/10" data-tp-close aria-label="Close">&times;</button>' +
        '</div>' +
        '<div class="flex-1 overflow-y-auto px-5 py-4">' +
          '<div class="' + H + '">Condition</div><div id="tpSt" class="grid grid-cols-2 gap-2 sm:grid-cols-3">' + stBtns() + '</div>' +
          (o.askExtract ? '<div id="tpExWrap" class="mt-3 rounded-lg border border-dashed border-accent/40 bg-accent/5 p-3 dark:border-accent/40 dark:bg-accent/10" hidden>' +
            '<div class="mb-2 text-[12.5px] font-semibold text-navy-800 dark:text-steel-50"><span id="tpExQ"></span> <span class="text-danger">*</span></div>' +
            '<div id="tpEx" class="grid gap-2 sm:grid-cols-2"></div></div>' : '') +
          '<div class="' + H + ' mt-4">Quick tags</div><div id="tpTags">' + tagBtns() + '</div>' +
          '<div class="' + H + ' mt-4">Note for this tooth <span class="font-normal normal-case tracking-normal">(optional)</span></div>' +
          '<textarea id="tpNote" maxlength="500" rows="2" class="w-full resize-y rounded-md border border-steel-200 px-3 py-2 text-[13px] dark:border-white/15 dark:bg-transparent dark:text-steel-100" placeholder="e.g. Cavity on the side near the gum"' + (o.readOnly ? ' disabled' : '') + '>' + esc(o.note || '') + '</textarea>' +
          '<div class="' + H + ' mt-4">Earlier records</div>' + window.toothHistoryHtml(o.history || []) +
        '</div>' +
        '<div class="flex justify-end gap-2 border-t border-dgt-ink-200 px-5 py-3.5 dark:border-white/10">' +
          '<button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 px-[15px] py-[9px] text-[13px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" data-tp-close>' + (o.readOnly ? 'Close' : 'Cancel') + '</button>' +
          (o.readOnly ? '' : '<button type="button" id="tpApply" class="inline-flex cursor-pointer items-center justify-center rounded-md bg-dgt-teal-600 px-[15px] py-[9px] text-[13px] font-semibold text-white hover:bg-dgt-teal-700 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-dgt-teal-600">Apply to Tooth ' + t + '</button>') +
        '</div>' +
      '</div>';
    const close = () => { el.remove(); document.removeEventListener('keydown', onKey, true); };
    // Apply only once something differs from how the tooth opened (a status-locked
    // pop-up from a service row can always apply).
    const tags0 = [...tags].sort().join('|'), note0 = (o.note || '').trim();
    const syncApply = () => {
      const btn = el.querySelector('#tpApply');
      if (!btn) return;
      const changed = o.lockStatus || st !== (o.status || '') || ex !== (typeof o.extracted === 'boolean' ? o.extracted : null) ||
        [...tags].sort().join('|') !== tags0 || el.querySelector('#tpNote').value.trim() !== note0;
      const missingEx = needEx() && ex === null;
      btn.disabled = !changed || missingEx;
      btn.title = missingEx ? 'Choose whether this was done today' : changed ? '' : 'Choose a condition, a tag or a note first';
    };
    const onKey = (e) => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
    el.addEventListener('click', (e) => {
      if (e.target === el || e.target.closest('[data-tp-close]')) return close();
      const sb = e.target.closest('[data-st]');
      // Clicking the chosen condition again undoes it: back to the tooth's
      // saved condition (o.baseStatus), not to Healthy.
      if (sb && !o.readOnly && !o.lockStatus) {
        const base = o.baseStatus != null ? o.baseStatus : (o.status || '');
        if (picked && sb.dataset.st === st) {
          st = base; picked = !!base && sb.dataset.st !== base;
          // Deselecting the condition also clears the quick tags and the note.
          tags.clear();
          el.querySelector('#tpTags').innerHTML = tagBtns();
          el.querySelector('#tpNote').value = '';
        } else { st = sb.dataset.st; picked = true; }
        el.querySelector('#tpSt').innerHTML = stBtns(); syncEx(); syncApply(); return;
      }
      const xb = e.target.closest('[data-ex]');
      if (xb) { ex = xb.dataset.ex === 'true'; syncEx(); syncApply(); return; }
      const tb = e.target.closest('[data-tag]');
      if (tb && !o.readOnly) {
        const g = tb.dataset.tag;
        if (tags.has(g)) tags.delete(g); else tags.add(g);
        el.querySelector('#tpTags').innerHTML = tagBtns();
        syncApply();
        return;
      }
      if (e.target.closest('#tpApply:not(:disabled)')) {
        const note = el.querySelector('#tpNote').value.trim();
        close();
        const out = { tooth: t, status: st, tags: window.TOOTH_TAGS.filter((g) => tags.has(g)), note };
        if (needEx()) out.extracted = ex;
        if (o.onApply) o.onApply(out);
      }
    });
    document.addEventListener('keydown', onKey, true);
    document.body.appendChild(el);
    el.querySelector('#tpNote').addEventListener('input', syncApply);
    syncEx();
    syncApply();
    (el.querySelector('[data-st][aria-pressed="true"]:not(:disabled)') || el.querySelector('[data-tp-close]')).focus();
  };

  // Chart state for one editor: current chart, latest tags / note per tooth, pending edits.
  window.toothChartState = function (chart, records) {
    const S = { chart: Object.assign({}, chart || {}), orig: Object.assign({}, chart || {}), records: records || [], pending: {}, info: {} };
    Object.entries(window.toothLatest(S.records)).forEach(([t, r]) => { S.info[t] = { tags: r.tags || [], note: r.note || '' }; });
    const info0 = JSON.parse(JSON.stringify(S.info));
    S.apply = (e) => {
      if (e.status) S.chart[e.tooth] = e.status; else delete S.chart[e.tooth];
      S.info[e.tooth] = { tags: e.tags, note: e.note };
      // Set back to exactly how it was loaded: nothing to save for this tooth.
      const i0 = info0[e.tooth] || { tags: [], note: '' };
      const same = (e.status || '') === (S.orig[e.tooth] || '') &&
        (e.tags || []).slice().sort().join('|') === (i0.tags || []).slice().sort().join('|') &&
        (e.note || '') === (i0.note || '');
      if (same) delete S.pending[e.tooth]; else S.pending[e.tooth] = e;
    };
    S.entries = () => Object.values(S.pending);
    S.open = (t, readOnly, onChange, extra) => window.toothDetailPopup(Object.assign({
      tooth: t, status: S.chart[t] || '', baseStatus: S.orig[t] || '', tags: (S.info[t] || {}).tags || [], note: (S.info[t] || {}).note || '',
      extracted: (S.pending[t] || {}).extracted,
      history: S.records.filter((r) => r.tooth === +t), readOnly,
      onApply: (e) => { S.apply(e); if (onChange) onChange(e); },
    }, extra || {}));
    return S;
  };

  // Chart step before Perform Procedure (2026-10-26): the dentist updates the
  // tooth chart for this visit, then the Services Availed pop-up opens.
  // after: true = the same chart again once the procedure is logged, for what
  // was done or new findings; Skip / Done both finish (2026-10-26).
  window.toothChartStep = async function ({ appt, onDone, after }) {
    const esc = window.esc;
    let d;
    try { d = await window.api('records.php?action=chart&appointment_id=' + appt.appointment_id); }
    catch (e) { window.toast(e.message, 'err'); if (after) window.closeModal(); return onDone(); }
    const S = window.toothChartState(d.tooth_chart, d.tooth_records);
    const baby = window.toothWantsBaby(d.date_of_birth, S.chart, S.records);
    const board = () => window.toothBoardHtml(S.chart, { clickable: true, baby, info: S.info, pending: S.pending });
    const BTN = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold';
    const goLabel = (n) => after ? (n ? 'Save Chart & Finish' : 'Done') : (n ? 'Save Chart & Continue' : 'Continue to Services');
    window.openModal(window.modalShell((after ? 'Update Tooth Chart After Procedure: ' : 'Update Tooth Chart: ') + esc(d.patient_name || appt.patient_name || ''),
      '<div class="rec-split">' +
        '<p class="m-0 text-[13px] text-steel-500 dark:text-steel-400">' + (after ? 'The procedure is saved. Update what you did or add new findings, if any. ' : '') + 'Click a tooth to set its condition, add quick tags or a note. Changes are saved under this visit (' + esc(window.fmtDate(String(d.visit_date).slice(0, 10))) + ') and shown at later visits.</p>' +
        '<div id="tcsBoard">' + board() + '</div>' + window.toothLegendHtml() +
        '<p id="tcsCount" class="m-0 text-[12.5px] font-semibold text-steel-500 dark:text-steel-400">No changes yet.</p>' +
      '</div>',
      '<button class="' + BTN + ' border border-steel-200 text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100" ' + (after ? 'id="tcsSkip"' : 'data-close') + '>' + (after ? 'Skip' : 'Cancel') + '</button>' +
      '<button class="' + BTN + ' bg-accent text-white hover:bg-accent-dark" id="tcsGo">' + goLabel(0) + '</button>'), { wide: true });
    if (after) document.getElementById('tcsSkip').addEventListener('click', () => { window.closeModal(); onDone(); });
    const wrap = document.getElementById('tcsBoard');
    const refresh = () => {
      wrap.innerHTML = board();
      const n = S.entries().length;
      document.getElementById('tcsCount').textContent = n ? n + (n === 1 ? ' tooth' : ' teeth') + ' changed this visit.' : 'No changes yet.';
      document.getElementById('tcsGo').textContent = goLabel(n);
      const root = document.getElementById('modalRoot');
      if (root && n) root.dataset.dirty = '1';
    };
    wrap.addEventListener('click', (e) => {
      const b = e.target.closest('.tooth-box');
      if (b) S.open(b.dataset.tooth, false, refresh);
    });
    document.getElementById('tcsGo').addEventListener('click', async () => {
      if (S.entries().length) {
        try {
          await window.api('records.php?action=save_teeth', { method: 'POST', body: { appointment_id: appt.appointment_id, tooth_entries: S.entries() } });
          window.toast('Tooth chart saved.', 'ok');
        } catch (e) { return window.toast(e.message, 'err'); }
      }
      if (after) window.closeModal();
      onDone();
    });
  };

  window.logProcedureModal = async function ({ appt, services, onDone }) {
    const id = appt.appointment_id;
    const concern = String(appt.notes || '').split(/treatment notes:/i)[0].trim();
    const concernText = concern && !/^none$/i.test(concern) ? concern : '';
    const bookedId = appt.service_id ? String(appt.service_id) : '';
    const resPaid = parseFloat(appt.reservation_fee_paid || 0) || 0;

    // Optional assisting-staff tag — today's on-duty dental assistants (non-account
    // staff; see api/assistants.php). Empty list => the field just shows a note.
    // Fetched in parallel; the modal opens at once and the picker fills in
    // when the list arrives (no wait before the pop-up shows).
    const asstReq = window.api('assistants.php?action=on_duty').then((r) => r.assistants || []).catch(() => []);
    const asstPreselect = appt.treatment_assistant_id ? String(appt.treatment_assistant_id) : '';
    const asstBlock = '<hr class="' + SEP + '"><div class="flex flex-col gap-1.5" id="t_asstWrap">' +
      '<label class="text-xs font-semibold text-steel-500">Assisting Staff <span class="text-steel-400">(optional)</span></label>' +
      '<span class="' + HINT + '">Loading assistant dentists on duty…</span></div>';

    // Optional follow-up recommendation (Treatment Plan module, migration 066).
    // Saved as a treatment plan locked to this visit's dentist; the patient
    // books it from their Appointments page ("Recommended Next Services").
    // Common dental follow-up intervals; values match FOLLOWUP_TIMEFRAMES in api/_bootstrap.php.
    const FU_TIMEFRAMES = [
      ['3 Days', 'denture adjustment check'],
      ['1 Week', 'extraction / surgery healing check'],
      ['2 Weeks', 'suture removal, next root canal session'],
      ['1 Month', 'braces adjustment'],
      ['6 Weeks', 'braces adjustment'],
      ['3 Months', 'gum maintenance, implant review'],
      ['6 Months', 'routine check-up and cleaning'],
    ];
    const FU_SEL = 'rounded-md border border-steel-200 px-2.5 py-2 text-[13px] outline-none focus:border-accent dark:border-white/15 dark:bg-transparent';
    const fuBlock = '<hr class="' + SEP + '"><div class="grid grid-cols-1 gap-3 sm:grid-cols-2">' +
      '<div class="flex flex-col gap-1.5"><label class="text-xs font-semibold text-steel-500" for="t_fu_svc">Recommended Follow-up Service <span class="text-steel-400">(optional)</span></label>' +
      '<select id="t_fu_svc" class="' + FU_SEL + '"><option value="">No follow-up needed</option>' +
      services.map((s) => '<option value="' + s.service_id + '">' + esc(s.service_name) + '</option>').join('') + '</select></div>' +
      '<div class="flex flex-col gap-1.5"><label class="text-xs font-semibold text-steel-500" for="t_fu_time">Suggested Timeframe</label>' +
      '<select id="t_fu_time" class="' + FU_SEL + '" disabled><option value="">Choose…</option>' +
      FU_TIMEFRAMES.map((t) => '<option value="' + t[0] + '">' + t[0] + '</option>').join('') + '</select></div>' +
      '<span class="' + HINT + ' sm:col-span-2">The patient can book it online with you from their Appointments page.</span></div>';

    // Performed services as rows (2026-10-11, adviser feedback): the booked
    // service is the first, fixed row; "+ Add Service" appends rows whose
    // dropdown hides every service already on the bill. The price always starts
    // blank for the dentist to type (list prices are only estimates); per-unit services (quantity_unit,
    // e.g. per tooth) also get a Qty box. Totals update live.
    const SVC = new Map(services.map((sv) => [String(sv.service_id), sv]));
    const IN = 'rounded-md border border-steel-200 px-2 py-1.5 text-[13px] outline-none focus:border-accent dark:border-white/15 dark:bg-transparent';
    const qtyHtml = (sv) => sv && sv.quantity_unit
      ? '<span class="flex h-8 items-center gap-1 text-xs text-steel-500">Qty <input type="number" class="svc-qty w-[58px] max-sm:w-[50px] ' + IN + '" value="1" min="1" max="99" step="1" aria-label="Number of ' + esc(window.unitPlural(sv.quantity_unit)) + '"> ×</span>'
      : '';
    const bookedSvc = SVC.get(bookedId);
    // The list "starts at" price, shown as a reference for the dentist only;
    // the Amount box still starts blank (2026-10-17).
    const fromHtml = (sv) => sv && +sv.base_price > 0
      ? 'Starts at ' + peso(sv.base_price) + (sv.quantity_unit ? ' per ' + esc(sv.quantity_unit) : '') : '';
    const FROM_CLS = 'svc-from block text-[11.5px] font-normal text-steel-400 dark:text-steel-500';
    // Tooth chart auto-update (2026-10-17): Extraction marks the picked teeth
    // Extracted on the patient's chart, Resto marks them Resto, Root Canal RCT.
    // Same name rule as toothChartEffect() in api/_bootstrap.php.
    const toothEffect = (sv) => !sv ? null
      : /extraction/i.test(sv.service_name) ? 'extract'
      : /root canal|\(rct\)/i.test(sv.service_name) ? 'rct'
      : /^resto\b/i.test(sv.service_name) ? 'resto' : null;
    const seq = (a, b) => { const o = []; for (let i = a; a > b ? i >= b : i <= b; i += a > b ? -1 : 1) o.push(i); return o; };
    // Each tooth button shows the tooth's shape; once picked it is drawn with
    // the result this service leaves on the chart (filling / root line / X).
    const TOOTH_BTN = 'svc-tooth relative flex h-[46px] w-[34px] max-sm:w-[30px] shrink-0 cursor-pointer flex-col items-center justify-end gap-0.5 rounded-md border border-steel-200 bg-white pb-0.5 pt-1 text-[10.5px] font-semibold tabular-nums text-navy-700 hover:border-accent dark:border-white/15 dark:bg-transparent dark:text-steel-100';
    const toothBtn = (t, eff) => '<button type="button" class="' + TOOTH_BTN + ' fx-' + eff + '" data-t="' + t + '" aria-pressed="false" title="Tooth ' + t + ': ' + esc(window.toothInfo(t).label) + '">' +
      window.toothSvg(t, eff) + '<span>' + t + '</span></button>';
    const JAW = 'w-11 shrink-0 text-[10px] font-bold uppercase tracking-wide text-steel-400 dark:text-steel-500';
    const toothRow = (jaw, left, right, eff) =>
      '<div class="flex items-center gap-1 max-sm:flex-col max-sm:items-stretch"><span class="' + JAW + '">' + jaw + '</span>' +
        '<div class="flex flex-1 flex-wrap items-center justify-center gap-1 max-sm:gap-y-1.5">' +
          '<div class="flex gap-1">' + left.map((t) => toothBtn(t, eff)).join('') + '</div>' +
          '<span class="mx-1 h-9 w-px bg-steel-300 max-sm:hidden dark:bg-white/20"></span>' +
          '<div class="flex gap-1">' + right.map((t) => toothBtn(t, eff)).join('') + '</div>' +
      '</div></div>';
    const SIDES = '<div class="flex justify-between pl-12 text-[10.5px] font-semibold text-steel-400 max-sm:hidden dark:text-steel-500"><span>&larr; Patient\'s right</span><span>Patient\'s left &rarr;</span></div>';
    // Baby teeth only for children: 12 or younger, or age unknown (2026-10-18).
    const kidAge = appt && appt.patient_age != null && appt.patient_age !== '' ? +appt.patient_age : null;
    const showBaby = kidAge === null || kidAge <= 12;
    const teethHtml = (sv) => {
      const eff = toothEffect(sv);
      if (!eff) return '';
      // The picker stays in the DOM as the row's record of teeth, but is hidden:
      // teeth are picked on the chart on the left (2026-10-26); this shows a summary.
      return '<p class="svc-teethsum m-0 mt-1 flex flex-wrap items-center gap-1.5 text-[12px] text-steel-500 empty:hidden dark:text-steel-400"></p>' +
        '<div class="svc-pick hidden mt-1 rounded-md bg-steel-50 px-2 py-2 dark:bg-white/5">' +
        '<div class="mb-1.5 flex flex-wrap items-center justify-between gap-2 text-[11.5px] text-steel-500 dark:text-steel-400">' +
          '<span>Click the tooth / teeth you treated <span class="text-danger">*</span></span>' +
          (showBaby ? '<label class="inline-flex cursor-pointer items-center gap-1"><input type="checkbox" class="svc-baby"> Baby teeth</label>' : '') +
        '</div>' +
        '<div class="flex flex-col gap-1">' + SIDES + toothRow('Upper', seq(18, 11), seq(21, 28), eff) + toothRow('Lower', seq(48, 41), seq(31, 38), eff) + '</div>' +
        (showBaby ? '<div class="svc-babyrows mt-2 hidden border-t border-dashed border-steel-200 pt-2 dark:border-white/10"><div class="flex flex-col gap-1">' +
          '<span class="pl-12 text-[10.5px] font-semibold text-steel-500 max-sm:pl-0 dark:text-steel-400">Baby teeth</span>' +
          toothRow('Upper', seq(55, 51), seq(61, 65), eff) + toothRow('Lower', seq(85, 81), seq(71, 75), eff) + '</div></div>' : '') +
      '</div>';
    };
    const pickedTeeth = (row) => [...row.querySelectorAll('.svc-tooth[aria-pressed="true"]')].map((b) => b.dataset.t);
    // Picking a tooth opens its chart pop-up (2026-10-26): the condition starts
    // at this service's result, and any quick tags / note are saved under this
    // visit right after the procedure is logged. A purple dot marks those teeth.
    const chartReq = window.api('records.php?action=chart&appointment_id=' + id).catch(() => null);
    const toothNotes = {};
    // CS = this visit's chart state; autoTeeth = teeth the service rows marked.
    let CS = null, autoTeeth = new Set();
    const setDot =(tb, e) => {
      const dot = tb.querySelector('.svc-dot');
      const has = e && ((e.tags || []).length || e.note);
      if (has && !dot) tb.insertAdjacentHTML('beforeend', '<span class="svc-dot absolute right-0.5 top-0.5 h-1.5 w-1.5 rounded-full bg-[#7c3aed]"></span>');
      if (!has && dot) dot.remove();
    };
    const openToothFor = async (tb, eff) => {
      const t = tb.dataset.t, d = await chartReq;
      const recs = (d && d.tooth_records) || [];
      const last = toothNotes[t] || (CS && CS.info[t]) || (window.toothLatest(recs)[t] || {});
      window.toothDetailPopup({
        tooth: t, status: eff, tags: last.tags || [], note: last.note || '',
        history: recs.filter((r) => r.tooth === +t), readOnly: false, lockStatus: true,
        onApply: (e) => { toothNotes[t] = e; setDot(tb, e); refreshChart(); },
      });
    };
    // Shows which teeth are marked; the Qty stays typed by the dentist (2026-10-26).
    const syncTeethQty = (row) => {
      const sv = SVC.get(row.dataset.sid), q = row.querySelector('.svc-qty');
      const sum = row.querySelector('.svc-teethsum');
      if (sum) {
        const ts = pickedTeeth(row);
        const eff = toothEffect(sv);
        const need = eff === 'extract' ? 'For Extraction' : eff === 'rct' ? 'Root Canal' : 'Resto';
        sum.innerHTML = !ts.length
          ? '<button type="button" class="svc-chartlink tab flex w-full cursor-pointer flex-wrap items-center gap-x-2.5 gap-y-1 rounded-lg border border-dashed border-[#f0a274] bg-[#fff6ef] px-3 py-2 text-left text-[12.5px] text-[#9a4613] outline-none hover:bg-[#ffeddf] focus-visible:ring-2 focus-visible:ring-[#f0a274] dark:border-[#f0a274]/50 dark:bg-[#f0a274]/10 dark:text-[#f5b38c]">' +
              '<svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3c-2.5 0-4 2-4 4.5 0 3 1.5 5 2 8 .4 2.4.8 5.5 2.5 5.5s1.6-3.5 2.5-5.5c.4-.9 1.6-.9 2 0 .9 2 .8 5.5 2.5 5.5s2.1-3.1 2.5-5.5c.5-3 2-5 2-8C21 5 19.5 3 17 3c-2 0-3 1-5 1S9 3 7 3z"/></svg>' +
              '<span class="min-w-0 flex-1"><b>Mark the tooth on the Tooth Chart</b><span class="block text-[11.5px] opacity-90">Set each treated tooth to ' + need + ' on the chart.</span></span>' +
              '<span class="shrink-0 font-semibold underline-offset-2 max-sm:basis-full max-sm:pl-[30px]">Open Tooth Chart &rarr;</span></button>'
          : ts.length
          ? '<span>Teeth:</span>' + ts.map((t) => '<span class="rounded-full bg-accent/10 px-2 py-0.5 font-semibold text-accent">' + t + '</span>').join('') +
            '<button type="button" class="svc-chartlink tab cursor-pointer font-semibold text-accent underline-offset-2 hover:underline">Edit on chart</button>'
          : '';
        // Qty follows the number of teeth marked whenever that number changes;
        // the dentist can still type over it afterwards (2026-10-26).
        const q = row.querySelector('.svc-qty');
        const n = String(ts.length);
        if (q && ts.length && row.dataset.tc !== n) q.value = n;
        row.dataset.tc = n;
      }
    };
    const rowHtml = (sv, fixed) =>
      '<div class="svc-row flex flex-wrap items-start gap-x-3 gap-y-2 max-sm:gap-x-2 max-sm:px-2.5 rounded-lg border border-steel-200 px-3 py-2.5 dark:border-white/10" data-sid="' + (sv ? sv.service_id : '') + '"' + (fixed ? ' data-fixed="1"' : '') + '>' +
        (fixed
          ? '<span class="min-w-[150px] flex-1 pt-1.5 max-sm:basis-full max-sm:pt-0 text-[13px] font-semibold text-navy-800 dark:text-steel-50">' + esc(sv.service_name) +
            ' ' + window.badgeHTML('Booked', 'approved') + '<span class="' + FROM_CLS + ' mt-0.5">' + fromHtml(sv) + '</span></span>'
          : '<span class="flex min-w-[170px] flex-1 flex-col gap-0.5 max-sm:basis-full"><select class="svc-sel ' + IN + '" aria-label="Service"></select>' +
            '<span class="' + FROM_CLS + '"></span></span>') +
        '<span class="svc-qtywrap">' + qtyHtml(sv) + '</span>' +
        '<input type="text" inputmode="decimal" data-money class="svc-price w-[110px] max-sm:w-auto max-sm:min-w-0 max-sm:flex-1 ' + IN + '" value="" placeholder="₱ Amount" aria-label="Price">' +
        '<span class="svc-line w-[92px] max-sm:w-auto max-sm:min-w-[64px] text-right leading-8 text-[13px] font-semibold tabular-nums text-navy-800 dark:text-steel-50">₱0.00</span>' +
        (fixed
          ? '<span class="w-7"></span>'
          : '<button type="button" class="svc-del mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-md text-danger hover:bg-danger-soft dark:hover:bg-danger/20" aria-label="Remove this service">' +
            '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>') +
        '<div class="svc-teethwrap basis-full empty:hidden">' + teethHtml(sv) + '</div>' +
      '</div>';
    const rows = bookedSvc ? rowHtml(bookedSvc, true) : '';

    // Services Availed with the tooth chart as a side panel of the same pop-up
    // (2026-10-26, adviser feedback): "Edit Tooth Chart" widens the pop-up and
    // slides the chart in from its right edge. Marking a tooth Resto, Root
    // Canal or Missing there adds it to that service row straight away.
    const DONE_BTN = 'inline-flex cursor-pointer items-center justify-center rounded-lg bg-dgt-teal-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-dgt-teal-700';
    // Legend: colour-coded condition badges, 2 columns.
    const LEGEND = '<div class="grid grid-cols-2 gap-2">' + window.TOOTH_STATUS.map((st) =>
      '<span class="t-lg' + ' t-lg-' + (st.key || 'healthy') + ' inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-[12px] font-medium">' +
        '<span class="inline-flex h-[18px] w-[13px] shrink-0 items-center justify-center' + (st.key ? ' t-' + st.key : '') + '">' + window.toothSvg(st.key === 'missing' || st.key === 'extract' ? 36 : 11, st.key) + '</span>' +
        '<span class="leading-tight sm:truncate">' + esc(st.label) + (st.tag ? ' (' + st.tag + ')' : '') + '</span></span>').join('') +
      '<span class="t-lg t-lg-note inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-[12px] font-medium"><span class="inline-flex w-[13px] justify-center"><span class="h-2 w-2 rounded-full bg-[#7c3aed]"></span></span>Has tags or a note</span>' +
      '</div>';
    const PANEL =
      '<div class="t-clip"><aside id="t_panel" class="t-panel flex border-l border-dgt-ink-200 bg-white dark:border-white/10 dark:bg-navy-800" aria-label="Tooth chart">' +
        '<div id="t_pBody" class="flex min-w-0 flex-1 flex-col" aria-hidden="true" inert>' +
        '<div class="flex items-start justify-between gap-3 border-b border-dgt-ink-200 px-5 py-3 dark:border-white/10">' +
          '<div class="min-w-0"><h3 id="t_pTitle" class="m-0 truncate text-[16px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">Tooth Chart: ' + esc(appt.patient_name) + '</h3>' +
          '<p class="m-0 text-[12.5px] text-steel-500 dark:text-steel-400">Click a tooth to record its condition. Work done this visit (Resto, Root Canal, Extraction) is added to the bill.</p></div>' +
          '<button type="button" data-p-close data-p-done class="' + DONE_BTN + ' shrink-0">Done</button>' +
        '</div>' +
        '<div class="t-pbody min-h-0 flex-1 overflow-y-auto px-4 py-3">' +
          '<div id="t_board" class="rounded-xl border border-dgt-ink-200 bg-dgt-ink-50/50 p-3 dark:border-white/10 dark:bg-white/5"><p class="' + HINT + '">Loading the tooth chart…</p></div>' +
        '</div>' +
        '<div class="border-t border-dgt-ink-200 px-5 py-3 dark:border-white/10">' +
          '<div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-steel-400">Legend</div>' + LEGEND +
          '<div class="mt-3 mb-1.5 flex items-baseline justify-between text-[11px] font-semibold uppercase tracking-wide text-steel-400"><span>Marked this visit</span><span id="t_pCount" class="normal-case tracking-normal"></span></div>' +
          '<ul id="t_marked" class="m-0 flex max-h-[96px] list-none flex-col gap-1 overflow-y-auto p-0"></ul>' +
        '</div>' +
        '</div>' +
      '</aside></div>';
    window.openModal('<div class="proc-wrap relative" id="t_wrap"><div class="proc-main min-w-0">' + window.modalShell('Services Availed: ' + esc(appt.patient_name),
      '<div id="t_form">' +
      '<div class="' + ALERT + ALERT_C.info + ' !mt-0"><strong>Patient’s Initial Concern:</strong> ' +
        (concernText ? esc(concernText) : '<span class="text-steel-400">None specified</span>') + '</div>' +
      '<div class="mb-1 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-steel-400"><span>Services Performed</span><span class="pr-[47px]">Amount</span></div>' +
      '<div class="flex flex-col gap-3" id="t_rows">' + rows + '</div>' +
      '<button type="button" id="t_add" class="mt-2 inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-dashed border-accent px-3 py-1.5 text-[13px] font-semibold text-accent hover:bg-accent/5 disabled:cursor-not-allowed disabled:opacity-50">+ Add Service</button>' +
      '<hr class="' + SEP + '"><div class="flex flex-col gap-1.5"><label class="text-xs font-semibold text-steel-500">Visit Notes</label>' +
      '<textarea id="t_notes" class="min-h-[72px] resize-y rounded-md border border-steel-200 px-2.5 py-2 text-[13px] outline-none focus:border-accent dark:border-white/15 dark:bg-transparent dark:text-steel-50" placeholder="Clinical findings, procedure details, prescriptions, follow-up…"></textarea>' +
      '<span class="' + HINT + '">Saved to this visit only; the initial concern above is left untouched. Teeth you mark on the Tooth Chart are added here automatically, and you can edit those lines.</span></div>' +
      fuBlock +
      asstBlock +
      '<hr class="' + SEP + '">' +
      '<div class="' + ROW_BTW + '"><span class="text-steel-400">Total Charges</span><span id="t_total">₱0.00</span></div>' +
      (resPaid > 0
        ? '<div class="' + ROW_BTW + '"><span class="text-steel-400">Less: reservation fee paid</span><span>−' + peso(resPaid) + '</span></div>'
        : '') +
      '<div class="' + ROW_BTW + ' mt-1.5"><strong>Balance to Cashier</strong><span class="' + BIG_NUM + '" id="t_net">₱0.00</span></div></div>',
      '<button class="' + BTN_GHOST + '" data-close id="t_cancel">Cancel</button>' +
      '<button class="' + BTN_OK + '" id="t_save">Proceed to Payment</button>') +
      '</div>' + PANEL + '</div>',
      { wide: true });

    const modal = document.getElementById('modalRoot');
    const rowsEl = document.getElementById('t_rows');
    const addBtn = document.getElementById('t_add');
    const allRows = () => [...rowsEl.querySelectorAll('.svc-row')];
    const lineQty = (row) => {
      const q = row.querySelector('.svc-qty');
      return q ? Math.min(Math.max(parseInt(q.value, 10) || 1, 1), 99) : 1;
    };
    // Rebuild every dropdown so it lists its own pick plus the services no
    // other row has taken (no duplicates on the bill).
    function syncOptions() {
      const taken = new Set(allRows().map((r) => r.dataset.sid).filter(Boolean));
      rowsEl.querySelectorAll('.svc-sel').forEach((sel) => {
        const mine = sel.closest('.svc-row').dataset.sid;
        sel.innerHTML = '<option value="">Choose a service…</option>' + services
          .filter((sv) => String(sv.service_id) === mine || !taken.has(String(sv.service_id)))
          .map((sv) => '<option value="' + sv.service_id + '"' + (String(sv.service_id) === mine ? ' selected' : '') + '>' +
            esc(sv.service_name) + '</option>').join('');
      });
      addBtn.disabled = taken.size >= services.length || allRows().some((r) => !r.dataset.sid);
    }
    function recalc() {
      let total = 0;
      allRows().forEach((row) => {
        const line = row.dataset.sid ? lineQty(row) * window.moneyVal(row.querySelector('.svc-price')) : 0;
        row.querySelector('.svc-line').textContent = peso(line);
        total += line;
      });
      document.getElementById('t_total').textContent = peso(total);
      document.getElementById('t_net').textContent = peso(Math.max(0, total - resPaid));
    }
    function addRow() {
      rowsEl.insertAdjacentHTML('beforeend', rowHtml(null, false));
      syncOptions(); recalc();
      const sel = rowsEl.lastElementChild.querySelector('.svc-sel');
      sel.focus();
    }
    addBtn.addEventListener('click', addRow);
    rowsEl.addEventListener('change', (e) => {
      const sel = e.target.closest('.svc-sel');
      if (!sel) return;
      const row = sel.closest('.svc-row');
      const sv = SVC.get(sel.value);
      row.dataset.sid = sel.value;
      row.querySelector('.svc-qtywrap').innerHTML = qtyHtml(sv);
      row.querySelector('.svc-from').innerHTML = fromHtml(sv);
      row.querySelector('.svc-teethwrap').innerHTML = teethHtml(sv);
      syncTeethQty(row);
      row.querySelector('.svc-price').value = '';
      row.classList.remove('!border-danger');
      syncOptions(); recalc(); refreshChart();
      if (sv && !row.dataset.noFocus) row.querySelector('.svc-price').focus();
    });
    rowsEl.addEventListener('change', (e) => {
      const baby = e.target.closest('.svc-baby');
      if (baby) baby.closest('.svc-teethwrap').querySelector('.svc-babyrows').classList.toggle('hidden', !baby.checked);
    });
    rowsEl.addEventListener('click', (e) => {
      const tb = e.target.closest('.svc-tooth');
      if (tb) {
        const on = tb.getAttribute('aria-pressed') !== 'true';
        tb.setAttribute('aria-pressed', on ? 'true' : 'false');
        const row = tb.closest('.svc-row');
        row.classList.remove('!border-danger');
        syncTeethQty(row); recalc();
        if (on) openToothFor(tb, toothEffect(SVC.get(row.dataset.sid)));
        else {
          const dot = tb.querySelector('.svc-dot');
          if (dot) dot.remove();
          if (!rowsEl.querySelector('.svc-tooth[aria-pressed="true"][data-t="' + tb.dataset.t + '"]')) delete toothNotes[tb.dataset.t];
        }
        refreshChart();
        return;
      }
      const del = e.target.closest('.svc-del');
      if (!del) return;
      del.closest('.svc-row').remove();
      if (!allRows().length) addRow(); else { syncOptions(); recalc(); }
      refreshChart();
    });
    // Price and Qty never go negative: the minus / plus / "e" keys are blocked,
    // and a pasted or arrowed negative value is turned positive (Qty floors at 1).
    rowsEl.addEventListener('keydown', (e) => {
      if (e.target.matches('.svc-price, .svc-qty') && ['-', '+', 'e', 'E'].includes(e.key)) e.preventDefault();
    });
    rowsEl.addEventListener('input', (e) => {
      if (!e.target.matches('.svc-price, .svc-qty')) return;
      const el = e.target;
      if (el.matches('.svc-qty') && el.value !== '' && parseFloat(el.value) < 0) el.value = String(Math.abs(parseFloat(el.value)));
      if (el.matches('.svc-qty') && el.value !== '' && parseInt(el.value, 10) < 1) el.value = '1';
      el.closest('.svc-row').classList.remove('!border-danger');
      recalc();
    });
    // No booked service on file (old walk-ins): start with one empty row.
    if (!allRows().length) addRow(); else { allRows().forEach(syncTeethQty); syncOptions(); recalc(); }

    asstReq.then((list) => {
      const wrap = document.getElementById('t_asstWrap');
      if (!wrap) return;
      wrap.innerHTML = '<label class="text-xs font-semibold text-steel-500" for="t_asst">Assisting Staff <span class="text-steel-400">(optional)</span></label>' +
        (list.length
          ? '<select id="t_asst" class="rounded-md border border-steel-200 px-2.5 py-2 text-[13px] outline-none focus:border-accent dark:border-white/15 dark:bg-transparent">' +
              '<option value="0">(None)</option>' +
              list.map((x) => '<option value="' + x.assistant_id + '"' + (String(x.assistant_id) === asstPreselect ? ' selected' : '') + '>' + esc(x.full_name) + '</option>').join('') +
            '</select><span class="' + HINT + '">Recorded on the visit for shift and workload reporting.</span>'
          : '<span class="' + HINT + '">No assistant dentists are marked on duty today.</span>');
    });

    const fuSvc = document.getElementById('t_fu_svc');
    const fuTime = document.getElementById('t_fu_time');
    fuSvc.addEventListener('change', () => { fuTime.disabled = !fuSvc.value; if (!fuSvc.value) fuTime.value = ''; });

    document.getElementById('t_save').addEventListener('click', async () => {
      const list = allRows();
      const noSvc = list.find((r) => !r.dataset.sid);
      if (noSvc) { noSvc.classList.add('!border-danger'); noSvc.querySelector('.svc-sel').focus(); return toast('Choose a service for every row, or remove the empty row.', 'err'); }
      const badPrice = list.find((r) => !(window.moneyVal(r.querySelector('.svc-price')) > 0));
      if (badPrice) { badPrice.classList.add('!border-danger'); badPrice.querySelector('.svc-price').focus(); return toast('Enter a price above ₱0 for every service.', 'err'); }
      if (!list.length) return toast('Add at least one service performed.', 'err');
      const noTeeth = list.find((r) => toothEffect(SVC.get(r.dataset.sid)) && !pickedTeeth(r).length);
      if (noTeeth) {
        noTeeth.classList.add('!border-danger');
        noTeeth.querySelector('.svc-teethwrap').scrollIntoView({ block: 'nearest' });
        openDrawer();
        return toast('Mark the tooth or teeth treated for ' + SVC.get(noTeeth.dataset.sid).service_name + ' on the tooth chart.', 'err');
      }
      const picked = list.map((r) => ({
        service_id: +r.dataset.sid,
        teeth: pickedTeeth(r),
        qty: lineQty(r),
        unit_price: window.moneyVal(r.querySelector('.svc-price')),
      }));
      if (fuSvc.value && !fuTime.value) { fuTime.focus(); return toast('Choose a suggested timeframe for the follow-up.', 'err'); }
      finish(picked);
    });

    // Teeth picked on the rows right now (no validation; for the chart only).
    function currentPicked() { return allRows().filter((r) => r.dataset.sid).map((r) => ({ service_id: +r.dataset.sid, teeth: pickedTeeth(r) })); }
    async function syncChart(picked) {
      const d = await chartReq;
      if (!d) return d;
      if (!CS) CS = window.toothChartState((d && d.tooth_chart) || {}, (d && d.tooth_records) || []);
      const orig = (d && d.tooth_chart) || {};
      // Teeth auto-marked by an earlier pass but no longer picked go back to how they were.
      autoTeeth.forEach((t) => {
        if (picked.some((p) => p.teeth.map(String).includes(t))) return;
        if (orig[t]) CS.chart[t] = orig[t]; else delete CS.chart[t];
        delete CS.pending[t];
      });
      autoTeeth = new Set();
      picked.forEach((p) => {
        const eff = toothEffect(SVC.get(String(p.service_id)));
        if (!eff) return;
        p.teeth.map(String).forEach((t) => {
          const n = toothNotes[t] || CS.info[t] || {};
          const ent = { tooth: +t, status: eff, tags: n.tags || [], note: n.note || '' };
          ent.extracted = true; // on a service row = done this visit
          CS.apply(ent);
          autoTeeth.add(t);
        });
      });
      return d;
    }
    // Side panel state: opening widens the pop-up (CSS on .proc-wrap.chart-open
    // in records_chart_style.php) and slides the chart in from its right edge.
    // Done, the X or Escape close it; changes are already applied.
    const wrap = document.getElementById('t_wrap');
    const panelEl = document.getElementById('t_panel');
    const pBody = document.getElementById('t_pBody');
    // Header pill (next to the title and the close X) opens / closes the chart.
    wrap.querySelector('.proc-main .modal-head .modal-close').insertAdjacentHTML('beforebegin',
      '<button type="button" id="t_chartBtn" class="tab inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-full bg-dgt-blue-500 px-3.5 py-1.5 text-[13px] max-sm:px-2.5 max-sm:text-[12px] font-semibold text-white shadow-sm outline-none hover:bg-dgt-blue-600 focus-visible:ring-2 focus-visible:ring-dgt-blue-300" aria-expanded="false" aria-controls="t_pBody">' +
        '<svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3c-2.5 0-4 2-4 4.5 0 3 1.5 5 2 8 .4 2.4.8 5.5 2.5 5.5s1.6-3.5 2.5-5.5c.4-.9 1.6-.9 2 0 .9 2 .8 5.5 2.5 5.5s2.1-3.1 2.5-5.5c.5-3 2-5 2-8C21 5 19.5 3 17 3c-2 0-3 1-5 1S9 3 7 3z"/></svg><span id="t_chartBtnTxt">Tooth Chart</span>' +
        '<span id="t_chartCount" class="hidden min-w-[18px] rounded-full bg-white px-1 text-center text-[11px] font-bold leading-[18px] text-dgt-blue-700"></span></button>');
    const chartBtn = document.getElementById('t_chartBtn');
    let isChartOpen = false;
    function setChart(on) {
      if (on === isChartOpen) return;
      isChartOpen = on;
      wrap.classList.toggle('chart-open', on);
      chartBtn.setAttribute('aria-expanded', String(on));
      document.getElementById('t_chartBtnTxt').textContent = on ? 'Close Tooth Chart' : 'Tooth Chart';
      if (on) { pBody.removeAttribute('inert'); pBody.setAttribute('aria-hidden', 'false'); }
      else { pBody.setAttribute('inert', ''); pBody.setAttribute('aria-hidden', 'true'); }
      if (on) setTimeout(() => { try { panelEl.querySelector('[data-p-done]').focus({ preventScroll: true }); } catch (e) { /* gone */ } }, 60);
      else if (pBody.contains(document.activeElement) || document.activeElement === document.body) { try { chartBtn.focus({ preventScroll: true }); } catch (e) { /* gone */ } }
    }
    const openDrawer = () => setChart(true);
    chartBtn.addEventListener('click', () => setChart(!isChartOpen));
    panelEl.addEventListener('click', (e) => { if (e.target.closest('[data-p-close]')) setChart(false); });
    rowsEl.addEventListener('click', (e) => { if (e.target.closest('.svc-chartlink')) openDrawer(); });
    // Escape closes the panel first (a tooth pop-up on top closes before that).
    const panelKey = (e) => {
      if (e.key !== 'Escape' || !isChartOpen || document.querySelector('.tooth-pop')) return;
      e.preventDefault(); e.stopImmediatePropagation(); setChart(false);
    };
    document.addEventListener('keydown', panelKey, true);
    const dropPanel = () => {
      document.removeEventListener('keydown', panelKey, true);
      document.removeEventListener('dgt:modalclose', dropPanel);
    };
    document.addEventListener('dgt:modalclose', dropPanel);

    const boardEl = document.getElementById('t_board');
    let BABY = null;
    // Visit Notes auto-fill (2026-10-26): one line per tooth changed on the
    // chart (condition, quick tags by group, note). The block is replaced while
    // it is still exactly what was generated; once the dentist edits it, it is
    // left alone so their wording is never overwritten.
    let autoNote = '';
    const toothNoteLine = (e) => {
      const tags = e.tags || [];
      // "lower right canine (eye tooth)" -> "lower right canine, eye tooth" (no nested brackets)
      const name = window.toothInfo(e.tooth).label.replace(/\s*\(([^)]*)\)/g, ', $1');
      const how = !['extract', 'resto', 'rct'].includes(e.status) ? ''
        : e.extracted === true ? (e.status === 'extract' ? ' (pulled this visit)' : ' (done this visit)')
        : e.extracted === false ? (e.status === 'extract' ? ' (to be pulled at a later visit)' : ' (already there before this visit)') : '';
      const parts = ['- Tooth ' + e.tooth + ' (' + name + '): ' + window.toothStatusLabel(e.status) + how + '.'];
      window.TOOTH_TAG_GROUPS.forEach(([name, list]) => {
        const hit = tags.filter((g) => list.includes(g));
        if (hit.length) parts.push(name + ': ' + hit.join(', ') + '.');
      });
      if (e.note) parts.push('Note: ' + e.note.trim().replace(/\.?$/, '.'));
      return parts.join('\n'); // one item per line
    };
    // Visit Notes grows to show all its text (typed or filled in), no inner scrollbar.
    function fitNotes() {
      const ta = document.getElementById('t_notes');
      if (!ta) return;
      ta.style.height = 'auto';
      ta.style.height = Math.max(ta.scrollHeight + 2, 72) + 'px';
    }
    document.getElementById('t_notes').addEventListener('input', fitNotes);
    function syncVisitNotes(list) {
      const ta = document.getElementById('t_notes');
      if (!ta) return;
      // Only teeth with a condition picked get a line: deselecting the
      // condition removes that tooth's whole entry (tags and note too).
      const withCond = list.filter((e) => e.status);
      const block = withCond.length ? 'Tooth chart:\n' + withCond.map(toothNoteLine).join('\n\n') : '';
      if (block === autoNote) return;
      const cur = ta.value;
      if (autoNote && cur.includes(autoNote)) {
        ta.value = cur.replace(autoNote, block).replace(/\n{3,}/g, '\n\n').trim();
      } else if (!autoNote || !cur.trim()) {
        if (!block) { autoNote = ''; return; }
        ta.value = cur.trim() ? cur.trim() + '\n\n' + block : block;
      } else {
        return; // the dentist edited the generated lines: keep theirs
      }
      autoNote = block;
      fitNotes();
    }
    function draw() {
      if (!CS) return;
      boardEl.innerHTML = window.toothBoardHtml(CS.chart, { clickable: true, baby: BABY, info: CS.info, pending: CS.pending });
      const list = CS.entries().slice().sort((x, y) => x.tooth - y.tooth);
      syncVisitNotes(list);
      const n = list.length;
      const cc = document.getElementById('t_chartCount');
      cc.textContent = String(n);
      cc.classList.toggle('hidden', !n);
      document.getElementById('t_pCount').textContent = n ? n + (n === 1 ? ' tooth' : ' teeth') : '';
      document.getElementById('t_marked').innerHTML = n ? list.map((e) =>
        '<li class="flex flex-wrap items-center gap-1.5 rounded-md bg-white px-2 py-1 text-[12px] text-navy-700 dark:bg-white/5 dark:text-steel-200">' +
          '<span class="font-semibold text-navy-900 dark:text-steel-50">Tooth ' + e.tooth + '</span><span class="text-steel-400">&middot;</span>' +
          esc(window.toothStatusLabel(e.status)) +
          ((e.tags || []).length ? '<span class="text-steel-400">&middot; ' + esc(e.tags.join(', ')) + '</span>' : '') +
          (e.note ? '<span class="text-steel-400">&middot; note</span>' : '') +
        '</li>').join('') : '<li class="text-[12px] text-steel-400">No teeth changed yet.</li>';
    }
    let chartBusy = Promise.resolve();
    function refreshChart() {
      chartBusy = chartBusy.then(() => syncChart(currentPicked())).then(draw).catch(() => {});
      return chartBusy;
    }
    chartReq.then((d) => {
      if (!d) {
        boardEl.innerHTML = '<p class="' + HINT + '">The tooth chart could not be loaded. You can still log the services.</p>';
        document.getElementById('t_marked').innerHTML = '';
        return;
      }
      BABY = window.toothWantsBaby(d.date_of_birth, d.tooth_chart || {}, d.tooth_records || []);
      refreshChart();
    });
    // A condition set on the chart that matches a service (Resto, Root Canal,
    // Missing = extracted) puts the tooth on that service's row when the row
    // is already on the bill (it never adds a row). Any other condition takes it off.
    function onChartApply(e) {
      const mr = document.getElementById('modalRoot');
      if (mr) mr.dataset.dirty = '1';
      const t = String(e.tooth);
      const orig = (chartOrig || {})[t] || '';
      // A tooth already like that before this visit (extracted / filled / RCT) is recorded, not billed.
      const target = e.status && (e.status !== orig || e.status === 'extract') && ['resto', 'rct', 'extract'].includes(e.status) &&
        e.extracted !== false ? e.status : null;
      let placed = false;
      allRows().forEach((r) => {
        const eff = toothEffect(SVC.get(r.dataset.sid));
        const btn = eff && r.querySelector('.svc-tooth[data-t="' + t + '"]');
        if (!btn) return;
        const want = eff === target && !placed;
        if (want) placed = true;
        if ((btn.getAttribute('aria-pressed') === 'true') !== want) {
          btn.setAttribute('aria-pressed', want ? 'true' : 'false');
          syncTeethQty(r);
        }
        setDot(btn, want ? e : null);
        if (want) showBabyRow(btn);
      });
      // Done this visit but that service isn't on the bill yet: add its row.
      if (target && !placed) {
        const sv = services.find((x) => toothEffect(x) === target);
        if (sv) {
          const row = rowFor(sv);
          const btn = row.querySelector('.svc-tooth[data-t="' + t + '"]');
          if (btn) { btn.setAttribute('aria-pressed', 'true'); setDot(btn, e); showBabyRow(btn); syncTeethQty(row); placed = true; }
          toast(sv.service_name + ' added to Services Performed for tooth ' + t + '. Enter its amount.', 'info');
        }
      }
      if (placed) toothNotes[t] = e;
      else { delete toothNotes[t]; autoTeeth.delete(t); }
      recalc(); refreshChart();
    }
    const showBabyRow = (btn) => {
      const wrap = btn.closest('.svc-babyrows');
      if (!wrap) return;
      wrap.classList.remove('hidden');
      const cb = btn.closest('.svc-teethwrap').querySelector('.svc-baby');
      if (cb) cb.checked = true;
    };
    // The row for a service: an existing one, the empty one, or a new one.
    function rowFor(sv) {
      const sid = String(sv.service_id);
      let row = allRows().find((r) => r.dataset.sid === sid);
      if (row) return row;
      row = allRows().find((r) => !r.dataset.sid && !r.dataset.fixed);
      if (!row) { rowsEl.insertAdjacentHTML('beforeend', rowHtml(null, false)); syncOptions(); row = rowsEl.lastElementChild; }
      const sel = row.querySelector('.svc-sel');
      row.dataset.noFocus = '1';
      sel.value = sid;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      delete row.dataset.noFocus;
      return row;
    }
    let chartOrig = null;
    chartReq.then((d) => { chartOrig = (d && d.tooth_chart) || {}; });
    boardEl.addEventListener('click', (e) => {
      const b = e.target.closest('.tooth-box');
      if (b && CS) CS.open(b.dataset.tooth, false, onChartApply, { askExtract: true });
    });
    async function finish(picked) {
      try {
        await api('treatments.php?action=log', { method: 'POST', body: {
          appointment_id: id,
          services_availed: picked,
          dentist_notes: document.getElementById('t_notes').value.trim(),
          assistant_id: document.getElementById('t_asst') ? +document.getElementById('t_asst').value : 0,
          follow_up: fuSvc.value ? { service_id: +fuSvc.value, timeframe: fuTime.value } : null,
          billing_status: 'Pending Billing',
        }});
      } catch (e) { return toast(e.message, 'err'); }
      let entries;
      // A tooth pulled this visit keeps the For Extraction (FX) mark, so it stays
      // apart from Missing (no tooth when first seen).
      if (CS) { await syncChart(picked); entries = CS.entries(); }
      else {
        const still = new Set(picked.flatMap((p) => p.teeth.map(String)));
        entries = Object.values(toothNotes).filter((e) => still.has(String(e.tooth)));
      }
      if (entries.length) {
        try { await api('records.php?action=save_teeth', { method: 'POST', body: { appointment_id: id, tooth_entries: entries } }); }
        catch (e) { toast('Procedure saved, but the tooth chart was not: ' + e.message, 'err'); }
      }
      closeModal(); toast('Appointment completed and sent to the cashier.');
      if (typeof onDone === 'function') onDone();
      window.refreshBellCount && window.refreshBellCount();
    }
  };

  let apptModalBusy = false;

  window.appointmentModal = async function (id, opts) {
    if (apptModalBusy) return;
    apptModalBusy = true;
    try {
      const { appointment: a } = await window.api('appointments.php?action=get&id=' + id);
      const svcList = a.services || [];
      const svc = svcList.map((s) =>
        '<tr><td>' + esc(s.service_name) + (+s.qty > 1 ? ' <span class="text-steel-400">× ' + (+s.qty) + (s.quantity_unit ? ' ' + esc(window.unitPlural(s.quantity_unit)) : '') + '</span>' : '') + '</td><td style="text-align:right">' + window.peso(s.price) + '</td></tr>'
      ).join('');
      const svcTotal = svcList.reduce((t, s) => t + (parseFloat(s.price) || 0), 0);
      const NONE = '<span class="text-steel-400">N/A</span>';
      // Top of the modal: once procedures are logged, show the full availed list
      // (not just the single service the patient originally booked).
      const serviceRows = svcList.length
        ? '<dt>Booked service</dt><dd>' + (esc(a.requested_service) || NONE) + '</dd>' +
          '<dt>Services availed</dt><dd>' + esc(svcList.map((s) => s.service_name).join(', ')) + '</dd>'
        : '<dt>Service</dt><dd>' + (esc(a.requested_service) || NONE) + '</dd>';
      const paymentsToDate = (parseFloat(a.amount_cash) || 0) + (parseFloat(a.amount_gcash) || 0) + (parseFloat(a.amount_bank) || 0);
      const bb = a.payment_id ? window.billingBadge(a) : null;
      const money = a.payment_id
        ? '<hr class="' + SEP + '"><h4 class="mb-2">Billing</h4>' +
          '<dl class="' + KV + '">' +
          '<dt>Total procedures amount</dt><dd>' + window.peso(a.total_amount) + '</dd>' +
          (parseFloat(a.discount) > 0 ? '<dt>Less: SC / PWD discount</dt><dd>−' + window.peso(a.discount) + '</dd>' : '') +
          (parseFloat(a.promo_discount_amount) > 0 ? '<dt>Less: ' + esc(a.promo_discount_label || 'Promo discount') + '</dt><dd>−' + window.peso(a.promo_discount_amount) + '</dd>' : '') +
          '<dt>Less: reservation fee credit</dt><dd>' + (parseFloat(a.reservation_fee_paid) > 0 ? '−' + window.peso(a.reservation_fee_paid) : window.peso(0)) + '</dd>' +
          '<dt>Less: Payments received</dt><dd>' + (paymentsToDate > 0 ? '−' + window.peso(paymentsToDate) : window.peso(0)) +
            (a.gcash_reference_number ? ' <span class="text-steel-400">(GCash ' + esc(a.gcash_reference_number) + ')</span>' : '') + '</dd>' +
          (parseFloat(a.reservation_refund) > 0 ? '<dt>Add: Excess reservation returned</dt><dd>+' + window.peso(a.reservation_refund) + '</dd>' : '') +
          '<dt>Remaining balance</dt><dd><strong>' + window.peso(a.remaining_balance) + '</strong></dd>' +
          '<dt>Billing status</dt><dd>' + window.badgeHTML(bb.label, bb.cls) + '</dd>' +
          '</dl>'
        : '';
      const bankBits = a.reservation_method === 'bank'
        ? [a.bank_branch, a.bank_account ? 'Acct ' + a.bank_account : ''].filter(Boolean).map(esc).join(' · ')
        : '';
      const resv = a.reservation_method
        ? '<dt>Reservation fee</dt><dd>' + window.peso(a.reservation_fee_paid) + ' via ' + esc(a.reservation_method_label || ({ gcash: 'GCash', bank: 'Bank Transfer', cash: 'Cash' })[a.reservation_method] || a.reservation_method) +
          (a.reservation_reference ? ' <span class="text-steel-400">(ref ' + esc(a.reservation_reference) + ')</span>' : '') +
          (bankBits ? ' <span class="text-steel-400">· ' + bankBits + '</span>' : '') +
          (a.reservation_receipt ? ' · <a href="/dgt_dental_clinic/uploads/receipts/' + encodeURIComponent(a.reservation_receipt) + '" target="_blank" rel="noopener">receipt</a>' : '') +
          '</dd>'
        : '';
      // Who submitted the cancellation reason — patient (self-service) vs the
      // clinic (receptionist decline / staff cancel). Falls back to the legacy
      // columns for rows created before the split.
      const patientCx = a.patient_cancellation_reason || '';
      const staffCx = a.staff_cancellation_reason || a.decline_reason || '';
      const cxByPatient = !!patientCx;
      const cxReason = patientCx || staffCx || a.cancellation_reason || '';
      const cxLabel = cxByPatient ? 'Patient’s reason' : 'Receptionist’s reason';
      const cxWho = cxByPatient ? ' by the patient' : (staffCx ? ' by the clinic' : '');
      // Single yellow callout carries the whole story — no duplicate detail row.
      // The operational reason never touches the CONCERN column.
      const changeNote =
        a.status === 'cancelled'
          ? '<div class="' + ALERT + ALERT_C.warn + '"><strong>This appointment was cancelled' + cxWho + '.</strong>' +
            (cxReason ? '<br>' + cxLabel + ': ' + esc(cxReason) : ' No reason on file.') + '</div>'
          : a.status === 'rescheduled'
            ? '<div class="' + ALERT + ALERT_C.warn + '"><strong>This appointment was rescheduled.</strong>' +
              // Staff only (patients don't get the oncall_* fields): the visit was
              // moved because the roaming dentist declined the on-call assignment.
              (a.oncall_declined_at
                ? '<div class="mt-1.5"><span class="inline-flex items-center rounded-full bg-danger-soft px-2 py-0.5 text-[11px] font-semibold text-danger dark:bg-danger/20">Roaming dentist declined</span> ' +
                  // Name only: the reason is already the Reschedule reason below.
                  esc(a.oncall_declined_by_name ? 'Dr. ' + a.oncall_declined_by_name : '') + '</div>'
                : '') +
              (a.reschedule_reason ? '<br>Reschedule reason: ' + esc(a.reschedule_reason) : '') + '</div>'
            : '';
      const reschedRows =
        a.status === 'rescheduled' && a.previous_scheduled_date
          ? '<dt>Original date</dt><dd>' + window.fmtDate(a.previous_scheduled_date) + '</dd>' +
            '<dt>New date &amp; time</dt><dd>' + window.fmtDate(a.scheduled_date) + ' · ' + window.fmtTime(a.time_slot) + '</dd>'
          : '<dt>Date &amp; time</dt><dd>' + window.fmtDate(a.scheduled_date) + ' · ' + window.fmtTime(a.time_slot) + '</dd>';
      // appointments.notes = patient's booking concern, then (after a procedure
      // is logged) the dentist's notes appended as "Treatment notes: …".
      const rawNotes = String(a.notes || '');
      const tnIdx = rawNotes.search(/treatment notes:/i);
      const concernPart = (tnIdx >= 0 ? rawNotes.slice(0, tnIdx) : rawNotes).trim();
      const treatmentPart = tnIdx >= 0 ? rawNotes.slice(tnIdx).replace(/^treatment notes:\s*/i, '').trim() : '';
      const notesRows =
        '<dt>Patient’s concern</dt><dd>' + (esc(concernPart) || '<span class="text-steel-400">None specified</span>') + '</dd>' +
        (treatmentPart ? '<dt>Visit notes <span class="text-steel-400">(dentist)</span></dt><dd>' + esc(treatmentPart) + '</dd>' : '');
      const body =
        changeNote +
        '<dl class="' + KV + '">' +
        '<dt>Patient</dt><dd>' + esc(a.patient_name) + '</dd>' +
        (a.patient_age ? '<dt>Age</dt><dd>' + esc(a.patient_age) + ' years old</dd>' : '') +
        (a.patient_phone ? '<dt>Patient phone</dt><dd>' + esc(a.patient_phone) + '</dd>' : '') +
        '<dt>Booked by</dt><dd>' + esc(a.patient_account) + (a.booking_for === 'other' ? ' <span class="text-steel-400">(' + esc(a.relationship || 'other') + ')</span>' : '') + '</dd>' +
        serviceRows +
        (a.insurance_provider ? '<dt>Insurance</dt><dd>' + esc(a.insurance_provider) + '</dd>' : '') +
        reschedRows +
        '<dt>Dentist</dt><dd>' + esc(a.dentist_name || 'Unassigned') + '</dd>' +
        (a.checkin_assistant_name ? '<dt>Checked in by assistant</dt><dd>' + esc(a.checkin_assistant_name) + '</dd>' : '') +
        (a.treatment_assistant_name ? '<dt>Treatment assistant</dt><dd>' + esc(a.treatment_assistant_name) + '</dd>' : '') +
        '<dt>Booking</dt><dd>' + window.srcBadge(a.booking_type) + '</dd>' +
        '<dt>Status</dt><dd>' + window.opStatus(a) + '</dd>' +
        resv +
        notesRows +
        '</dl>' +
        (svc ? '<hr class="' + SEP + '"><h4 class="mb-2">Procedures</h4><div class="overflow-x-auto"><table class="' + TABLE + '"><thead><tr><th>Procedure</th><th style="text-align:right">Price</th></tr></thead><tbody>' + svc + '</tbody>' +
          '<tfoot><tr><td style="text-align:right"><strong>Total</strong></td><td style="text-align:right"><strong>' + window.peso(svcTotal) + '</strong></td></tr></tfoot>' +
          '</table></div>' : '') +
        money;
      window.openModal(window.modalShell(((opts && opts.title) || 'Appointment') + (a.ref_label ? ' ' + esc(a.ref_label) : ''), body),
        { stack: true });
    } catch (err) { window.toast(err.message, 'err'); }
    finally { apptModalBusy = false; }
  };

  // Global delegation: any element with data-view="<appointment_id>" opens the drill-down.
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-view]');
    if (b) { e.preventDefault(); window.appointmentModal(b.dataset.view); }
  });

  /* ---------- Numeric-only contact fields ---------- */
  // Any phone / contact-number input (type="tel") accepts digits only — typing
  // or pasting anything else is silently dropped. Delegated so it also covers
  // inputs created later inside modals.
  document.addEventListener('input', (e) => {
    const el = e.target;
    if (!el || el.tagName !== 'INPUT' || el.type !== 'tel') return;
    const cleaned = el.value.replace(/\D+/g, '');
    if (cleaned === el.value) return;
    const drop = el.value.length - cleaned.length;
    const pos = Math.max((el.selectionStart || cleaned.length) - drop, 0);
    el.value = cleaned;
    try { el.setSelectionRange(pos, pos); } catch (_) { /* unsupported on some inputs */ }
  });

  /* ---------- Name fields: letters only (2026-10-18) ---------- */
  // First / last name inputs (ids ending in _first / _last, e.g. u_first,
  // w_last, p_first) and the invoice "Received from" box drop anything but
  // letters (accents and ñ included), spaces, hyphens, apostrophes and
  // periods (plus a comma for "Last, First"), max 50. Server: validPersonName().
  const NAME_SEL = 'input[id$="_first"], input[id$="_last"], input[id$="first"][id^="u_r"], input[id$="last"][id^="u_r"], #f_from';
  window.cleanName = (v, allowComma) => String(v)
    .replace(allowComma ? /[^\p{L}\p{M} .,'-]+/gu : /[^\p{L}\p{M} .'-]+/gu, '')
    .replace(/^[^\p{L}]+/u, '')
    .replace(/ {2,}/g, ' ')
    .slice(0, 50);
  document.addEventListener('input', (e) => {
    const el = e.target;
    if (!el || el.tagName !== 'INPUT' || !el.matches(NAME_SEL)) return;
    const cleaned = window.cleanName(el.value, el.id === 'f_from');
    if (cleaned === el.value) return;
    const drop = el.value.length - cleaned.length;
    const pos = Math.max((el.selectionStart || cleaned.length) - drop, 0);
    el.value = cleaned;
    try { el.setSelectionRange(pos, pos); } catch (_) { /* unsupported on some inputs */ }
  });

  /* ---------- Proper capitalization for names / addresses ---------- */
  // Mirrors properCase() in includes/functions.php (the server still applies it
  // on save). A word typed ALL CAPS or all lowercase becomes Title Case
  // ("rex" -> "Rex", "O'NEIL" -> "O'Neil", "mary-ann" -> "Mary-Ann"); mixed
  // case ("McDonald"), words with digits ("B-6L-47") and roman numerals stay.
  window.properCase = (s) => String(s || '').trim().replace(/\s+/g, ' ').split(' ').map((w) => {
    if (!w || /\d/.test(w)) return w;
    if (/^(II|III|IV|VI|VII|VIII|IX)\.?$/i.test(w)) return w.toUpperCase();
    const lo = w.toLowerCase();
    if (w !== w.toUpperCase() && w !== lo) return w;
    return lo.replace(/(^|[-'\u2019(/])(\p{L})/gu, (m, a, b) => a + b.toUpperCase());
  }).join(' ');
  // Applied when the person leaves the field: first / middle / last name,
  // relationship and address inputs (ids like w_first, u_rlast, b_rel, f_address).
  const PROPER_ID = /^[a-z]+_(r?first|r?middle|r?last|rel|address)$/;
  document.addEventListener('focusout', (e) => {
    const el = e.target;
    if (!el || el.tagName !== 'INPUT' || el.readOnly || !(PROPER_ID.test(el.id) || /^(first|middle|last)_name$/.test(el.name))) return;
    const v = window.properCase(el.value);
    if (v !== el.value) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); }
  });

  /* ---------- Clinic-closed guard for appointment date pickers ---------- */
  // Native <input type="date"> can't grey out weekdays, so we reject the pick
  // after the fact: any Sunday or clinic-wide blocked date is cleared with a
  // message. Use ONLY on booking / reschedule date fields — never on a date of
  // birth, payment date, report range, etc.
  window.wireClinicDate = function (input, opts = {}) {
    if (!input || input.dataset.clinicDateWired) return;
    input.dataset.clinicDateWired = '1';
    const say = (msg) => {
      if (opts.msgEl) opts.msgEl.innerHTML = '<div class="' + window.alertCls('error') + '">' + window.esc(msg) + '</div>';
      else window.toast(msg, 'err');
    };
    input.addEventListener('change', async () => {
      if (opts.msgEl) opts.msgEl.innerHTML = '';
      const v = input.value;
      if (!v) return;
      if (new Date(v + 'T00:00:00').getDay() === 0 && !window.SUNDAY_OPEN) {
        input.value = '';
        say('The clinic is closed on Sundays. Please choose another day.');
        return;
      }
      try {
        const r = await window.api('appointments.php?action=slots&date=' + v);
        if (r.blocked) {
          input.value = '';
          say(r.reason || 'The clinic is closed on this date.');
        }
      } catch (_) { /* backend still validates on submit */ }
    });
  };

  /* ---------- Philippine mobile-number field ---------- */
  window.PH_MOBILE_RE  = /^09\d{9}$/;
  window.PH_MOBILE_MSG = 'Please enter a valid 11-digit Philippine mobile number starting with 09 (e.g., 09171234567)';

  // Force digits-only + 11-char + `09` prefix on a phone / GCash-number input.
  // Non-digits are already stripped by the global type="tel" handler. On a bad
  // (non-empty, not 09XXXXXXXXX) value: red border + inline error + (optional)
  // disabled submit button; the error clears the moment a valid value is typed.
  // opts: { errEl, submitBtn }.  input._phoneOk(allowEmpty=true) reports validity.
  window.wirePhoneField = function (input, opts = {}) {
    if (!input || input.dataset.phoneWired) return;
    input.dataset.phoneWired = '1';
    input.type = 'tel';
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('maxlength', '11');
    input.setAttribute('pattern', '09[0-9]{9}');
    if (input.value) input.value = input.value.replace(/\D+/g, '');   // normalise any prefilled value

    let err = opts.errEl || null;
    if (!err && opts.noErrEl !== true) {
      err = document.createElement('div');
      err.className = 'field-err';
      err.hidden = true;
      input.insertAdjacentElement('afterend', err);
    }
    const validate = () => {
      const v = input.value.trim();
      const bad = v !== '' && !window.PH_MOBILE_RE.test(v);
      input.classList.toggle('is-invalid', bad);
      if (err) { err.hidden = !bad; if (bad) err.textContent = window.PH_MOBILE_MSG; }
      if (opts.submitBtn) opts.submitBtn.disabled = bad;
    };
    input.addEventListener('input', validate);
    input.addEventListener('blur', validate);
    input._phoneOk = (allowEmpty = true) => {
      const v = input.value.trim();
      return (allowEmpty && v === '') || window.PH_MOBILE_RE.test(v);
    };
    input._phoneValidate = validate;
  };

  /* ---------- Service picker with coloured prices (2026-10-11) ----------
     A plain <option> can't colour part of its text, so this keeps the real
     <select id> (sr-only, all form code reads it) and draws a list beside it:
     name on the left, grey "starts at" + the base price in green.
       svcPickerHtml(id, services, selectedId, inputCls, placeholder)
       wireSvcPicker(id)   // after the markup is in the DOM
     The visible button is #<id>Btn (point a guided-form step at it). */
  const svcPrice = (s) => '₱' + Number(s.base_price).toLocaleString('en-PH', { maximumFractionDigits: 2 }) + (s.quantity_unit ? '/' + window.esc(s.quantity_unit) : '');
  window.svcPickerHtml = function (id, services, selectedId, inputCls, placeholder) {
    const esc = window.esc, ph = placeholder || 'Select service…';
    return '<select class="sr-only" id="' + id + '" tabindex="-1" aria-hidden="true"><option value="">' + esc(ph) + '</option>' +
      services.map((s) => '<option value="' + s.service_id + '"' + (String(s.service_id) === String(selectedId || '') ? ' selected' : '') + '>' + esc(s.service_name) +
        (+s.base_price > 0 ? ' · starts at ' + svcPrice(s) : '') + '</option>').join('') + '</select>' +
      '<div class="relative" id="' + id + 'Pick" data-ph="' + esc(ph) + '">' +
        '<button type="button" id="' + id + 'Btn" class="' + inputCls + ' flex w-full cursor-pointer items-center justify-between gap-2 text-left" aria-haspopup="listbox" aria-expanded="false">' +
          '<span id="' + id + 'Lbl" class="min-w-0 truncate"></span>' +
          '<svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-steel-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>' +
        '</button>' +
        '<ul id="' + id + 'List" role="listbox" class="absolute left-0 right-0 top-full z-30 mt-1 hidden max-h-[320px] overflow-y-auto rounded-md border border-steel-200 bg-white py-1 shadow-lg dark:border-white/15 dark:bg-navy-900">' +
          services.map((s) => '<li role="option" tabindex="-1" data-v="' + s.service_id + '" class="svc-opt flex cursor-pointer items-baseline justify-between gap-3 px-3 py-2 text-[13px] text-navy-800 hover:bg-steel-50 focus:bg-steel-50 focus:outline-none aria-selected:bg-dgt-blue-50 dark:text-steel-100 dark:hover:bg-white/5 dark:focus:bg-white/5 dark:aria-selected:bg-white/10">' +
            '<span>' + esc(s.service_name) + (+s.duration_minutes > 0 ? '<span class="ml-1.5 whitespace-nowrap text-[11.5px] font-normal text-steel-400 dark:text-steel-500">&middot; ' + esc(window.fmtDuration(s.duration_minutes, s.quantity_unit)) + '</span>' : '') + '</span>' +
            (+s.base_price > 0 ? '<span class="shrink-0 whitespace-nowrap text-[12.5px] font-semibold text-ok"><span class="font-normal text-steel-400">starts at </span>' + svcPrice(s) + '</span>' : '') + '</li>').join('') +
        '</ul>' +
      '</div>';
  };
  window.wireSvcPicker = function (id) {
    const g = (x) => document.getElementById(x);
    const sel = g(id), btn = g(id + 'Btn'), list = g(id + 'List'), wrap = g(id + 'Pick');
    if (!sel || !btn || !list || sel.tagName !== 'SELECT') return;
    const opts = () => [...list.querySelectorAll('.svc-opt')];
    const paint = () => {
      const li = opts().find((x) => x.dataset.v === sel.value);
      const lbl = g(id + 'Lbl');
      lbl.innerHTML = li ? li.innerHTML.replace('<span>', '<span class="mr-2">') : '<span class="text-steel-400">' + window.esc(wrap.dataset.ph) + '</span>';
      lbl.className = 'flex min-w-0 items-baseline justify-between gap-2 truncate' + (li ? ' flex-1' : '');
      opts().forEach((x) => x.setAttribute('aria-selected', String(x.dataset.v === sel.value)));
    };
    const open = (on) => {
      list.classList.toggle('hidden', !on);
      btn.setAttribute('aria-expanded', String(on));
      if (on) {
        const cur = opts().find((x) => x.dataset.v === sel.value) || opts()[0];
        if (cur) { cur.focus({ preventScroll: true }); cur.scrollIntoView({ block: 'nearest' }); }
        // Bring the whole open list into view inside the scrolling pop-up.
        try { list.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); } catch (e) { /* */ }
      }
    };
    const pick = (v) => {
      sel.value = v;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      paint(); open(false); btn.focus();
    };
    btn.addEventListener('click', () => open(list.classList.contains('hidden')));
    btn.addEventListener('keydown', (e) => { if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); open(true); } });
    list.addEventListener('click', (e) => { const li = e.target.closest('.svc-opt'); if (li) pick(li.dataset.v); });
    list.addEventListener('keydown', (e) => {
      const all = opts(), i = all.indexOf(document.activeElement);
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); const n = all[Math.min(Math.max(i + (e.key === 'ArrowDown' ? 1 : -1), 0), all.length - 1)]; if (n) n.focus(); }
      else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (all[i]) pick(all[i].dataset.v); }
      else if (e.key === 'Escape' || e.key === 'Tab') { e.stopPropagation(); open(false); btn.focus(); }
    });
    const outside = (e) => {
      if (!wrap.isConnected) { document.removeEventListener('click', outside, true); return; }
      if (!wrap.contains(e.target)) open(false);
    };
    document.addEventListener('click', outside, true);
    sel.addEventListener('change', paint);
    paint();
  };

  /* ---------- Guided forms (2026-10-10, adviser feedback) ----------
     Highlights one step at a time on a long form: the current step's control
     pulses (.guide-active), finished fields turn green with a check after
     their label (.guide-done), and later steps are dimmed and inert
     (.guide-locked) until the earlier ones are done. A "Step 2 of 4: ..."
     bar sits at the top. When the user finishes a step with a pick (select,
     radio, calendar, a fixed-length number), focus moves to the next one.
       guideForm(root, barEl, steps, opts)
         opts.noScroll: highlight the next step with the pulsing ring only and
         never scroll the form to it (patient booking, 2026-10-17).
         steps: [{ label, fields: [{ wrap, ctl, ok, focus, auto, autoWindow }] }]
         (noLock: never dim this field; reveal: unused now (the field is scrolled to the top); auto: move focus on once this field is done; autoWindow: how long
         after the user's last action that may still happen, default 2.5 s)
         each value may be a function (re-read every time, since panels are
         rebuilt); a field whose wrap is missing or hidden is skipped. A step
         with no visible fields is skipped too. The last step is usually the
         submit button: { label, fields: [{ wrap: btn, ok: () => false }] }.
     Re-evaluates on input/change/click and on any DOM change inside root. */
  window.guideForm = function (root, bar, steps, opts) {
    if (!root) return null;
    const noScroll = !!(opts && opts.noScroll);
    // A missing element (a panel not built yet) just reads as null.
    const val = (x) => { try { return typeof x === 'function' ? x() : x; } catch (e) { return null; } };
    const shown = (el) => !!(el && el.isConnected && el.getClientRects().length);
    let prevActive = null, userAt = 0, lastTarget = null, queued = false, pendingFocus = null;
    let prevShown = new Set();   // field definitions visible last time
    let touched = new Set();   // every element styled last time, to clean up ones that stop being a step

    function setCls(el, cls, on) { if (el && el.classList.contains(cls) !== on) el.classList.toggle(cls, on); }
    function setInert(el, on) {
      if (!el || el.tagName === 'BUTTON') { if (el && el.hasAttribute('aria-disabled') !== on) { if (on) el.setAttribute('aria-disabled', 'true'); else el.removeAttribute('aria-disabled'); } return; }
      if (el.inert !== on) el.inert = on;
    }
    function focusField(f) {
      const c = val(f.ctl);
      const el = val(f.focus) || (Array.isArray(c) ? c[0] : c) || val(f.wrap);
      if (!el || !el.focus) return;
      try { el.focus({ preventScroll: true }); } catch (e) { /* gone */ }
      if (noScroll) return;   // the pulsing ring alone shows the next step
      // Bring the next field up to the top of the scrolling form (2026-10-11:
      // it always moves down to it, even when the field is already partly on
      // screen), so the rest of the form below it comes into view too.
      const box = val(f.wrap) || el;
      let sc = box.parentElement;
      while (sc && sc !== document.body && !/(auto|scroll)/.test(getComputedStyle(sc).overflowY)) sc = sc.parentElement;
      const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
      if (sc && sc !== document.body) {
        const top = Math.max(0, Math.min(sc.scrollHeight - sc.clientHeight,
          sc.scrollTop + box.getBoundingClientRect().top - sc.getBoundingClientRect().top - 12));
        if (Math.abs(top - sc.scrollTop) > 2) { try { sc.scrollTo({ top, behavior }); } catch (e) { /* */ } }
        return;
      }
      try { box.scrollIntoView({ block: 'start', behavior }); } catch (e) { /* */ }
    }

    function compute() {
      queued = false;
      if (!root.isConnected) { mo.disconnect(); return; }
      const live = [];
      steps.forEach((s, si) => {
        const fields = s.fields.map((f) => ({ f, wrap: val(f.wrap) })).filter((x) => shown(x.wrap));
        if (fields.length) live.push({ s, si, fields });
      });
      let activeStep = -1, active = null;
      live.forEach((st, i) => {
        st.fields.forEach((x) => {
          x.ok = !!val(x.f.ok);
          if (!x.ok && !active) { active = x; activeStep = i; }
        });
      });
      const now = new Set();
      live.forEach((st, i) => {
        st.fields.forEach((x) => {
          // ctl may be a list (the Month / Day / Year boxes pulse together).
          const c0 = val(x.f.ctl);
          const ctls = (Array.isArray(c0) ? c0 : [c0 || x.wrap]).filter(Boolean);
          now.add(x.wrap); ctls.forEach((c) => now.add(c));
          // noLock: the field stays usable (not dimmed) even before its turn.
          const locked = activeStep >= 0 && i > activeStep && !val(x.f.noLock);
          const isActive = x === active;
          // A step that is still dimmed shows no check yet, even when it
          // already holds a value (e.g. a date kept from an earlier visit).
          const done = x.ok && !locked;
          setCls(x.wrap, 'guide-locked', locked);
          setInert(x.wrap, locked);
          setCls(x.wrap, 'guide-done', done && x.wrap.tagName !== 'BUTTON');
          ctls.forEach((c) => {
            setCls(c, 'guide-done-ctl', done && c !== x.wrap);
            setCls(c, 'guide-active', isActive);
          });
        });
      });
      touched.forEach((el) => {
        if (now.has(el)) return;
        ['guide-locked', 'guide-done', 'guide-done-ctl', 'guide-active'].forEach((c) => setCls(el, c, false));
        setInert(el, false);
      });
      touched = now;
      if (bar) {
        const n = live.length, cur = activeStep < 0 ? n - 1 : activeStep;
        const lbl = live[cur] ? val(live[cur].s.label) : '';
        const html = '<span><b>Step ' + (cur + 1) + ' of ' + n + ':</b> ' + window.esc(lbl) + '</span>' +
          '<span class="guide-dots" aria-hidden="true">' + live.map((st, i) => '<i class="' + (i < cur ? 'ok' : i === cur ? 'on' : '') + '"></i>').join('') + '</span>';
        if (bar._html !== html) { bar._html = html; bar.innerHTML = html; }
      }
      // Move focus forward only right after the user finished the previous
      // field with a pick, never backwards and never while a pop-up is open.
      const flat = [];
      live.forEach((st) => st.fields.forEach((x) => flat.push(x.f)));
      const aF = active ? active.f : null;
      if (aF && prevActive && aF !== prevActive && flat.indexOf(aF) > flat.indexOf(prevActive) &&
          flat.indexOf(prevActive) >= 0 && val(prevActive.auto) && Date.now() - userAt < (val(prevActive.autoWindow) || 2500)) pendingFocus = aF;
      // A pick that reveals a new required field in its own step (choosing
      // GCash shows the reference box) moves focus there too.
      if (aF && aF !== prevActive && !prevShown.has(aF) && lastTarget && Date.now() - userAt < 2500) {
        const st = live[activeStep];
        if (st && st.fields.some((x) => x !== active && x.wrap.contains(lastTarget))) pendingFocus = aF;
      }
      if (pendingFocus && !document.querySelector('.slot-pop')) {
        const f = pendingFocus; pendingFocus = null;
        if (f === aF) focusField(f);
      }
      prevActive = aF;
      prevShown = new Set(flat);
    }
    const queue = () => { if (!queued) { queued = true; requestAnimationFrame(compute); } };
    // Any user action counts (a time picked in the slot pop-up is outside root).
    const mark = (ev) => { if (!root.isConnected) { ['input', 'change', 'click'].forEach((t) => document.removeEventListener(t, mark, true)); return; } userAt = Date.now(); lastTarget = ev && ev.target; queue(); };
    ['input', 'change', 'click'].forEach((t) => document.addEventListener(t, mark, true));
    const mo = new MutationObserver(queue);
    mo.observe(root, { subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'hidden', 'style', 'value'] });
    // The slot pop-up lives on <body>, so watch for it closing too.
    const bodyMo = new MutationObserver(() => { if (!root.isConnected) { bodyMo.disconnect(); return; } if (pendingFocus) queue(); });
    bodyMo.observe(document.body, { childList: true });
    compute();
    return { refresh: queue };
  };

  /* ---------- Debounce ---------- */
  window.debounce = function (fn, ms = 350) {
    let t;
    return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), ms); };
  };

  /* ---------- Live "reference number already used?" check ---------- */
  // Wire a GCash / bank reference input to api/check_reference_number.php.
  // opts: { errEl, submitBtn, format:{re,msg}, excludePayment, excludeCounter }
  // On a duplicate: red border + inline error + disable submit. On a fresh,
  // well-formed value: a green "available" note. The submit-time validators
  // still run server-side as the real gate.
  window.wireRefCheck = function (input, opts = {}) {
    if (!input) return;
    const err = opts.errEl || null;
    const setState = (kind, msg) => {
      input.classList.toggle('is-invalid', kind === 'bad');
      if (err) {
        if (!msg) { err.hidden = true; err.className = 'field-err'; err.textContent = ''; }
        else { err.hidden = false; err.className = 'field-err' + (kind === 'ok' ? ' ok' : ''); err.textContent = msg; }
      }
      if (opts.submitBtn) opts.submitBtn.disabled = (kind === 'bad');
    };

    const check = async () => {
      const v = input.value.trim();
      if (!v) { setState('clear', ''); return; }
      if (opts.format && !opts.format.re.test(v)) { setState('bad', opts.format.msg); return; }
      try {
        const q = new URLSearchParams({ ref: v });
        if (opts.excludePayment) q.set('exclude_payment', opts.excludePayment);
        if (opts.excludeCounter) q.set('exclude_counter', opts.excludeCounter);
        const r = await window.api('check_reference_number.php?' + q.toString());
        if (r.available) setState('ok', '✓ Reference number available');
        else setState('bad', 'This reference number has already been used for another transaction');
      } catch (_) { setState('clear', ''); /* network hiccup — don't block */ }
    };
    const run = window.debounce(check, 400);

    input.addEventListener('keyup', () => { setState('clear', ''); run(); });
    input.addEventListener('blur', run);
    input._refCheckReset = () => setState('clear', '');
  };

  /* ---------- Live "does this email account exist?" check ---------- */
  // Wire an email input to api/verify_email.php — admin "Add User" and the
  // receptionist walk-in "New patient" modal (modules/admin/users.php's
  // #u_email, modules/receptionist/dashboard.php's #w_email). opts: { errEl, submitBtn }.
  // Fires on blur only (not on every keystroke, unlike wireRefCheck) and only
  // for a syntactically valid, reasonably-sized address — works for any
  // domain (Gmail, Outlook, Yahoo, a custom business domain, ...; this was a
  // Gmail-only check before being generalized). A module-scope in-memory
  // cache means re-focusing/re-blurring the same value never re-checks it
  // twice — this app has no client router, so app.js (and this cache with it)
  // stays loaded across every nav.js in-app navigation for the whole session.
  // Server-side, api/users.php action=add / api/records.php action=create
  // re-run the identical (cache-backed) check as the real gate — this
  // client-side hint exists purely so the person filling the form sees the
  // problem before submitting, not as the only enforcement.
  //
  // OTP fallback (2026-09): when the provider chain can't confirm the address
  // either way, api/verify_email.php reports `otp_required:true` instead of
  // silently accepting it (see includes/email_verify.php) and this function
  // reveals a small inline widget — a code input + Verify/Resend — right
  // under the field. The submit button stays disabled until either a
  // provider confirms the address or the OTP is verified; the person being
  // created an account for in these two flows (a walk-in patient in person,
  // a new hire being onboarded) is normally reachable on the spot to read the
  // code back.
  const emailCheckCache = new Map();
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  window.wireEmailCheck = function (input, opts = {}) {
    if (!input) return;
    let err = opts.errEl || null;
    if (!err) {
      err = document.createElement('div');
      err.className = 'field-err';
      err.hidden = true;
      input.insertAdjacentElement('afterend', err);
    }
    const setState = (kind, msg) => {
      input.classList.toggle('is-invalid', kind === 'bad');
      if (!msg) { err.hidden = true; err.className = 'field-err'; err.textContent = ''; }
      else {
        err.hidden = false;
        err.className = 'field-err' + (kind === 'ok' ? ' ok' : (kind === 'checking' || kind === 'otp') ? ' checking' : '');
        err.textContent = msg;
      }
      if (opts.submitBtn) opts.submitBtn.disabled = (kind === 'bad' || kind === 'otp');
    };

    // Only a real provider source (or a solved OTP challenge) means the
    // mailbox was actually confirmed — show the green ok state only then.
    // 'fail_open' also reports valid:true, but that's a "couldn't tell,
    // don't block" guess, not a real confirmation, so it renders as 'clear'
    // (no message), same as skip — it never reaches here any more, though,
    // since verify_email.php now turns that case into an OTP challenge.
    const CONFIRMED_SOURCES = ['abstract', 'emailverify', 'mailboxlayer', 'otp_verified'];

    let otpWrap = null;
    const hideOtp = () => { if (otpWrap) otpWrap.hidden = true; };
    const showOtp = (devOtp) => {
      if (!otpWrap) {
        otpWrap = document.createElement('div');
        otpWrap.className = 'mt-2 rounded-lg border border-dgt-blue-200 bg-dgt-blue-50 p-2.5 text-xs dark:border-dgt-blue-800 dark:bg-dgt-blue-900/30';
        otpWrap.innerHTML =
          '<p class="mb-1.5 text-dgt-ink-700 dark:text-dgt-ink-200">We could not automatically confirm this address. Enter the 6-digit code just sent to it.</p>' +
          '<div class="flex flex-wrap items-center gap-1.5">' +
            '<input type="text" inputmode="numeric" maxlength="6" placeholder="Code" ' +
              'class="w-24 rounded border border-dgt-ink-200 px-2 py-1 text-sm dark:border-dgt-ink-600 dark:bg-dgt-ink-800 dark:text-white">' +
            '<button type="button" class="otp-verify-btn rounded bg-dgt-teal-600 px-2.5 py-1 font-semibold text-white hover:bg-dgt-teal-700">Verify Code</button>' +
            '<button type="button" class="otp-resend-btn rounded border border-dgt-ink-300 px-2.5 py-1 text-dgt-ink-600 hover:bg-dgt-ink-50 dark:border-dgt-ink-600 dark:text-dgt-ink-300 dark:hover:bg-dgt-ink-700">Resend</button>' +
          '</div>' +
          '<p class="otp-msg mt-1.5 hidden"></p>';
        err.insertAdjacentElement('afterend', otpWrap);

        const codeInput = otpWrap.querySelector('input');
        const verifyBtn = otpWrap.querySelector('.otp-verify-btn');
        const resendBtn = otpWrap.querySelector('.otp-resend-btn');
        const msgEl = otpWrap.querySelector('.otp-msg');
        const showMsg = (m, bad) => {
          msgEl.textContent = m || '';
          msgEl.classList.toggle('hidden', !m);
          msgEl.classList.toggle('text-danger', !!bad);
          msgEl.classList.toggle('text-dgt-ink-500', !bad);
        };

        verifyBtn.addEventListener('click', async () => {
          const code = codeInput.value.trim();
          const v = input.value.trim().toLowerCase();
          if (!code) return;
          verifyBtn.disabled = true;
          try {
            await window.api('verify_email_otp.php', { method: 'POST', body: { email: v, otp: code } });
            emailCheckCache.set(v, { valid: true, source: 'otp_verified' });
            hideOtp();
            setState('ok', '✓ Email address verified');
          } catch (e) {
            showMsg(e.message || 'Incorrect code. Please try again.', true);
          } finally {
            verifyBtn.disabled = false;
          }
        });

        let cooldown = 0;
        resendBtn.addEventListener('click', async () => {
          if (cooldown > 0) return;
          const v = input.value.trim().toLowerCase();
          resendBtn.disabled = true;
          try {
            const r = await window.api('resend_email_otp.php', { method: 'POST', body: { email: v } });
            showMsg(r.dev_otp ? ('A new code was sent. (dev) code: ' + r.dev_otp) : 'A new code was sent.', false);
          } catch (e) {
            showMsg(e.message || 'Could not resend the code.', true);
          }
          cooldown = 30;
          const tick = () => {
            cooldown--;
            resendBtn.textContent = cooldown > 0 ? ('Resend (' + cooldown + 's)') : 'Resend';
            if (cooldown > 0) setTimeout(tick, 1000); else resendBtn.disabled = false;
          };
          tick();
        });
      }
      otpWrap.hidden = false;
      otpWrap.querySelector('input').value = '';
      const msgEl = otpWrap.querySelector('.otp-msg');
      if (devOtp) { msgEl.textContent = '(dev) code: ' + devOtp; msgEl.classList.remove('hidden', 'text-danger'); msgEl.classList.add('text-dgt-ink-500'); }
      else { msgEl.classList.add('hidden'); }
    };

    const applyResult = (r) => {
      if (r.otp_required) {
        setState('otp', 'We could not automatically confirm this address. Verification code required.');
        showOtp(r.dev_otp);
        if (r.mail_failed && otpWrap) {
          const m = otpWrap.querySelector('.otp-msg');
          m.textContent = r.mail_error || "We couldn't send the code right now. Please tap Resend in a moment.";
          m.classList.remove('hidden', 'text-dgt-ink-500'); m.classList.add('text-danger');
        }
        return;
      }
      hideOtp();
      if (!r.valid) { setState('bad', 'This email address does not exist.'); return; }
      if (CONFIRMED_SOURCES.includes(r.source)) { setState('ok', '✓ Email address verified'); return; }
      setState('clear', '');
    };

    input.addEventListener('blur', async () => {
      const v = input.value.trim().toLowerCase();
      if (!v || !EMAIL_RE.test(v) || v.length < 6) { setState('clear', ''); hideOtp(); return; }
      if (emailCheckCache.has(v)) {
        applyResult(emailCheckCache.get(v));
        return;
      }
      // A cache miss means a real network round trip to a third-party API is
      // about to happen (can take a second or two) — show a neutral "checking"
      // state so the field doesn't look frozen/unresponsive in the meantime.
      setState('checking', 'Checking…');
      const isStale = () => input.value.trim().toLowerCase() !== v;
      try {
        const r = await window.api('verify_email.php', { method: 'POST', body: { email: v } });
        // A failed send isn't cached as the result, so the next blur tries again.
        if (!r.mail_failed) emailCheckCache.set(v, { valid: r.valid, source: r.source, otp_required: !!r.otp_required });
        if (isStale()) return; // the admin already changed the field before this resolved
        applyResult(r);
      } catch (_) {
        if (isStale()) return;
        setState('clear', ''); /* network hiccup — don't block */
      }
    });
  };

  /* ---------- Mobile sidebar ---------- */
  const menuToggle = document.getElementById('menuToggle');
  // Off-canvas drawer below 1280px: the backdrop (layout.php, shown by CSS
  // while #sidebar.open) and Escape both close it.
  const setDrawer = (open) => {
    const sb = document.getElementById('sidebar');
    if (!sb) return;
    sb.classList.toggle('open', open);
    if (menuToggle) {
      menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }
  };
  if (menuToggle) menuToggle.addEventListener('click', () => setDrawer(!document.getElementById('sidebar').classList.contains('open')));
  document.getElementById('sbBackdrop')?.addEventListener('click', () => setDrawer(false));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.getElementById('sidebar')?.classList.contains('open')) setDrawer(false);
  });
  // Every page load, and a Back / Forward restore from the browser cache,
  // starts with the drawer closed (it only opens from the burger button).
  window.addEventListener('pageshow', () => setDrawer(false));
  window.setDrawer = setDrawer;
  // Rotating a tablet to landscape (>= 1280px) shows the fixed sidebar, so drop the drawer state.
  window.matchMedia('(min-width: 1280px)').addEventListener('change', (m) => { if (m.matches) setDrawer(false); });

  // Responsive tables (.rtable): below 1280px each row is shown as a card
  // (CSS in tailwind.php) with every cell labelled by its column header. The
  // label comes from data-label, stamped here from the <thead> whenever a
  // page (re)renders its rows, so page scripts need no changes.
  function stampTableLabels(root) {
    (root || document).querySelectorAll('table.rtable').forEach((t) => {
      const heads = [...t.querySelectorAll('thead th')].map((th) => th.textContent.trim());
      t.querySelectorAll('tbody tr').forEach((tr) => {
        [...tr.children].forEach((td, i) => {
          if (!td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i] || '');
        });
      });
    });
  }
  window.stampTableLabels = stampTableLabels;
  // Watch the page and the modal (tables inside pop-ups, e.g. a patient's
  // Appointment History).
  ['app-content', 'modalRoot'].forEach((rid) => {
    const rtRoot = document.getElementById(rid);
    if (!rtRoot) return;
    let rtQueued = false;
    new MutationObserver(() => {
      if (rtQueued) return;
      rtQueued = true;
      requestAnimationFrame(() => { rtQueued = false; stampTableLabels(rtRoot); });
    }).observe(rtRoot, { childList: true, subtree: true });
    stampTableLabels(rtRoot);
  });

  /* ---------- Desktop sidebar collapse (every role) ----------
   * .sb-toggle-header (its own slim row under the brand block) collapses the
   * sidebar. Re-expanding has no separate button — while collapsed, the logo
   * badge itself (#sbLogoToggle) doubles as the control (CSS swaps it to the
   * same panel glyph on hover); its click handler below only ever expands,
   * and no-ops while already expanded so the logo just behaves like a logo. */
  const SB_KEY = 'dgt_sidebar_collapsed';
  // `auto-fill`/`minmax()` card grids (patient Services' #svcGrid, patient
  // Appointments' .appt-grid) size their column COUNT off the container's own
  // width, not the viewport's — so while #appShellContent's margin-left is
  // mid-transition, the browser re-solves that count on every animation
  // frame and cards visibly snap between column counts instead of just
  // smoothly resizing, which reads as "awkward" jank the plainer tables/
  // grid-cols-N pages don't have (their column count is a fixed Tailwind
  // breakpoint, so it never depends on the animating width). Freezing the
  // column count for the duration of the transition keeps every column an
  // ordinary fluid `1fr` track — it only resizes, never re-counts — until
  // the sidebar settles, at which point releasing the override lets the
  // grid re-resolve once, cleanly, for the final width.
  const REFLOW_GRID_SEL = '#svcGrid, .appt-grid';
  const setSbCollapsed = (on) => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const grids = reduced ? [] : document.querySelectorAll(REFLOW_GRID_SEL);
    grids.forEach((g) => {
      const cols = getComputedStyle(g).gridTemplateColumns.split(' ').length;
      g.style.gridTemplateColumns = 'repeat(' + cols + ', minmax(0, 1fr))';
    });
    document.body.classList.toggle('sb-collapsed', on);
    localStorage.setItem(SB_KEY, on ? '1' : '0');
    if (grids.length) {
      setTimeout(() => { grids.forEach((g) => { g.style.gridTemplateColumns = ''; }); }, 320);
    }
  };
  document.querySelectorAll('.sb-toggle').forEach((btn) => {
    btn.addEventListener('click', () => setSbCollapsed(!document.body.classList.contains('sb-collapsed')));
  });
  const sbLogo = document.getElementById('sbLogoToggle');
  if (sbLogo) {
    sbLogo.addEventListener('click', (e) => {
      if (!document.body.classList.contains('sb-collapsed')) return;   // just a logo while expanded
      e.preventDefault();    // the brand block is a real <a> for patients — don't navigate
      e.stopPropagation();
      setSbCollapsed(false);
    });
  }

  /* ---------- Dark mode toggle (every role) ----------
   * header.php's #themeToggle flips a `dark` class on <html> (Tailwind's
   * darkMode:'class'), persisted so it survives a reload — an inline script in
   * layout.php's <head> applies the stored choice (or the OS preference, on a
   * first visit) before <body> paints, so there's no flash of the wrong theme.
   * The button itself doesn't re-render on toggle; its sun/moon icon swap is
   * pure CSS keyed off html.dark (see the <style> block in header.php).
   *
   * The switch itself animates as a circle expanding from the button (ported
   * from Magic UI's "Animated Theme Toggler" — https://magicui.design — to
   * plain DOM/CSS since this app has no React/build step): the native View
   * Transitions API snapshots the before/after paint, and a WAAPI animation
   * clips the new snapshot to a circle growing from the button's centre out
   * to the farthest corner. tailwind.php disables the browser's own default
   * cross-fade on ::view-transition-*(root) so only this clip-path plays.
   * Falls back to the old instant toggle on a browser without
   * document.startViewTransition, or when the user prefers reduced motion. */
  // The sun/moon/auto appearance menu now lives in assets/js/theme-menu.js
  // (loaded globally from layout.php); it owns #themeToggle.

  /* ---------- Post-login entrance (every role) ----------
   * The other half of login.php's "launch" transition: that page plays a
   * zoom+fade exit on its own auth card right before the real (native) form
   * submission, then sets a one-shot sessionStorage flag. app.js loads fresh
   * on every full page load — including the redirect a successful sign-in
   * lands on, for every role — but is NOT re-run by nav.js's in-app partial
   * swaps, so this only ever fires once per login, never on a later in-app
   * navigation or a plain reload. There is no shared DOM between login.php
   * and this page to morph (different documents entirely, no client
   * router), so this is a matching fade/rise entrance on the shell that
   * greets the user here, not a literal continuation of the same element.
   * (The patient's post-login destination, index.php, doesn't load app.js —
   * it already has its own scroll/mount reveal animations, so nothing
   * further is needed there.) */
  (function () {
    var FLAG = 'dgt_just_logged_in';
    var justLoggedIn = false;
    try {
      justLoggedIn = sessionStorage.getItem(FLAG) === '1';
      sessionStorage.removeItem(FLAG);
    } catch (e) {}
    if (!justLoggedIn) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var targets = [];
    var sidebarEl = document.getElementById('sidebar');
    var headerEl = document.getElementById('topHeader');
    // Only the desktop sidebar joins the entrance. Below 1280px it is the
    // closed drawer, and animating its transform would override the CSS
    // that keeps it off-screen, so it slid open on the first page after login.
    if (sidebarEl && window.matchMedia('(min-width: 1280px)').matches) targets.push(sidebarEl);
    if (headerEl) targets.push(headerEl);
    var content = document.getElementById('app-content');
    if (content) targets = targets.concat(Array.prototype.slice.call(content.children));
    if (!targets.length) return;

    targets.forEach(function (el, i) {
      el.animate(
        [{ opacity: 0, transform: 'translateY(16px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: 420, delay: i * 60, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'backwards' }
      );
    });
  })();
})();
