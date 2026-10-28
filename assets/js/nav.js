/* ============================================================
   AJAX page loader — fetch() + history.pushState()
   Intercepts clicks on [data-nav] links, swaps #app-content,
   re-runs inline <script> from the fragment, updates sidebar.
   ============================================================ */
(function () {
  'use strict';

  const content = document.getElementById('app-content');
  if (!content) return;

  // Only the newest navigation may render: a slower, older page that
  // finishes later is dropped (and its request aborted) instead of
  // replacing the page the user moved on to.
  let navSeq = 0, navCtl = null;

  async function load(url, push) {
    const seq = ++navSeq;
    if (navCtl) navCtl.abort();
    navCtl = typeof AbortController === 'function' ? new AbortController() : null;
    const prevUrl = location.href;
    setActive(url);   // the sidebar answers the click at once
    if (window.netBusy) window.netBusy(true);
    // Dim only if the page is slow, so a quick switch doesn't flicker.
    const dim = setTimeout(() => { if (seq === navSeq) content.style.opacity = '0.5'; }, 150);
    content.setAttribute('aria-busy', 'true');
    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' }, signal: navCtl ? navCtl.signal : undefined });
      const html = await res.text();
      if (seq !== navSeq) return;

      // Split fragment HTML from trailing <script> blocks.
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const scripts = [...tmp.querySelectorAll('script')];
      scripts.forEach((s) => s.remove());

      if (window.closeSlotPopup) window.closeSlotPopup('nav');
      content.innerHTML = tmp.innerHTML;

      scripts.forEach((old) => {
        const s = document.createElement('script');
        if (old.src) s.src = old.src;
        else s.textContent = old.textContent;
        document.body.appendChild(s).remove();
      });

      const title = res.headers.get('X-Page-Title')
        || content.querySelector('[data-page-title]')?.dataset.pageTitle
        || 'Dashboard';
      document.title = title + ' · DGT One Dental Center - Bulacan';

      // A server redirect (e.g. to the Patient Information Record) shows the page it landed on.
      const shown = res.redirected ? res.url : url;
      if (push || res.redirected) history[push ? 'pushState' : 'replaceState']({ url: shown }, '', shown);
      setActive(shown);
      window.scrollTo(0, 0);
      if (window.setDrawer) window.setDrawer(false);
      else {
        document.getElementById('sidebar')?.classList.remove('open');
        document.getElementById('menuToggle')?.setAttribute('aria-expanded', 'false');
      }
      document.querySelectorAll('.bell-panel, .profile-menu').forEach((p) => { p.hidden = true; });
    } catch (err) {
      if (seq !== navSeq || (err && err.name === 'AbortError')) return;   // replaced by a newer click
      setActive(prevUrl);
      const msg = navigator.onLine === false
        ? 'You are offline. The page could not be loaded.'
        : 'Failed to load page. Please try again.';
      window.toast ? window.toast(msg, 'err') : alert(msg);
    } finally {
      clearTimeout(dim);
      if (window.netBusy) window.netBusy(false);
      if (seq === navSeq) {
        content.style.opacity = '';
        content.removeAttribute('aria-busy');
      }
    }
  }

  function setActive(url) {
    const path = new URL(url, location.origin).pathname;
    document.querySelectorAll('#sidebar .nav-item[data-nav]').forEach((a) => {
      a.classList.toggle('active', new URL(a.href, location.origin).pathname === path);
    });
  }

  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-nav]');
    if (!a) return;
    if (a.origin !== location.origin) return;
    e.preventDefault();
    if (new URL(a.href).pathname === location.pathname) return;
    load(a.href, true);
  });

  window.addEventListener('popstate', (e) => {
    const url = (e.state && e.state.url) || location.href;
    load(url, false);
  });

  // Allow module scripts to request a reload of the current page.
  window.reloadPage = () => load(location.href, false);
})();
