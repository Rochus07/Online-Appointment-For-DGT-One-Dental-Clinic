<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['admin']);
$PAGE_TITLE = 'Services';
$ACTIVE = 'services';
$PAGE_FULL = true;   // admin screens fill the full viewport width

$btnBase    = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:cursor-not-allowed disabled:opacity-50';
$btnPrimary = "$btnBase bg-accent text-white hover:bg-accent-dark";
$btnGhost   = "$btnBase border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5";
$inputCls   = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
// table-fixed + explicit column widths so a full-bleed page spreads the columns
// evenly instead of packing the short ones (Price / Status) together.
$tableCls   = 'w-full table-fixed border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 '
    . '[&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5 [&_.amt]:text-right [&_th.amt]:text-right';

ob_start();
?>
<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="mb-3 flex flex-wrap gap-2">
        <button class="<?= $btnPrimary ?>" id="addServiceBtn">Add New Service</button>
        <button class="<?= $btnGhost ?>" id="archiveServiceBtn">View Archived Services</button>
    </div>
    <div class="mb-3.5 flex flex-wrap items-center gap-3">
        <input type="search" id="svcSearch" placeholder="Search services by name&hellip;"
               class="<?= $inputCls ?> min-w-[220px] flex-1">
        <select id="svcCat" class="<?= $inputCls ?> min-w-[200px]" aria-label="Filter by category"><option value="">All categories</option></select>
        <span id="svcCount" class="text-xs text-steel-400 dark:text-steel-500"></span>
    </div>
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>" id="svcTable">
            <thead><tr>
                <th class="w-[60px]">Image</th><th class="w-[15%]">Service Name</th><th class="w-[26%]">Description</th><th class="w-[22%]">Features</th>
                <th class="w-[18%]">Categories</th><th class="amt w-[110px]">Price</th><th class="w-[110px]">Usual Time</th><th class="w-[92px]">Status</th><th class="w-[136px]">Actions</th>
            </tr></thead>
            <tbody><tr><td colspan="9" class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="svcPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Service pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const IMG_BASE = '/dgt_dental_clinic/uploads/services/';
  let cache = [];
  const PG = { page: 1 };   // pager (window.pageSlice + fitRows)
  let SPECS = {};
  let QTY_UNITS = [];
  // "₱1,500.00 / tooth" for a per-unit service, plain price otherwise.
  const priceLabel = (s) => peso(s.base_price) + (s.quantity_unit ? ' <span class="text-[11px] font-normal text-steel-400 dark:text-steel-500">/ ' + esc(s.quantity_unit) + '</span>' : '');

  const BTN     = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:cursor-not-allowed disabled:opacity-50';
  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50';
  const PRIMARY = ' bg-accent text-white hover:bg-accent-dark';
  const GHOST   = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const WARN    = ' bg-warn text-white hover:brightness-95';
  const FG      = 'flex flex-col gap-1.5';
  const LBL     = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
  const INP     = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
  const BADGE   = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const EMPTY8  = '<tr><td colspan="9" class="py-7 text-center text-steel-400 dark:text-steel-500">';

  const specNames = (csv) => (csv || '').split(',').filter(Boolean).map((k) => SPECS[k] || k);
  const featureList = (txt) => (txt || '').split('\n').map((s) => s.trim()).filter(Boolean);

  function thumb(s) {
    return s.image
      ? '<img src="' + IMG_BASE + encodeURIComponent(s.image) + (s.image_v ? '?v=' + s.image_v : '') + '" alt="" class="block h-[46px] w-[46px] rounded-md object-cover dark:bg-white/5">'
      : '<span class="grid h-[46px] w-[46px] place-items-center rounded-md bg-steel-100 text-xl dark:bg-white/5">🦷</span>';
  }

  async function load() {
    const tb = document.querySelector('#svcTable tbody');
    try {
      const res = await api('services.php');
      cache = res.services;
      SPECS = res.specialties || {};
      QTY_UNITS = res.quantity_units || [];
      const archived = cache.filter((s) => s.is_active == 0).length;
      g('archiveServiceBtn').textContent = 'View Archived Services' + (archived ? ' (' + archived + ')' : '');
      fillCats();
      render();
    } catch (e) { tb.innerHTML = EMPTY8 + esc(e.message) + '</td></tr>'; }
  }

  // Category filter: only categories an active service is in, with counts.
  function fillCats() {
    const sel = g('svcCat'), cur = sel.value, count = {};
    cache.filter((s) => s.is_active == 1).forEach((s) => (s.specialties || '').split(',').filter(Boolean).forEach((k) => (count[k] = (count[k] || 0) + 1)));
    sel.innerHTML = '<option value="">All categories</option>' + Object.keys(count)
      .sort((a, b) => (SPECS[a] || a).localeCompare(SPECS[b] || b))
      .map((k) => '<option value="' + esc(k) + '">' + esc(SPECS[k] || k) + ' (' + count[k] + ')</option>').join('');
    sel.value = count[cur] ? cur : '';
  }

  function render() {
    const tb = document.querySelector('#svcTable tbody');
    const q = g('svcSearch').value.trim().toLowerCase();
    const cat = g('svcCat').value;
    const active = cache.filter((s) => s.is_active == 1);
    const match = active.filter((s) => (!q || s.service_name.toLowerCase().includes(q))
      && (!cat || (s.specialties || '').split(',').includes(cat)));
    g('svcCount').textContent = (q || cat) ? match.length + ' of ' + active.length + ' shown' : '';
    const { rows } = window.pageSlice(match, PG, window.fitRows(g('svcTable')), g('svcPager'), render);
    tb.innerHTML = rows.length ? rows.map((s) =>
      '<tr>' +
      '<td>' + thumb(s) + '</td>' +
      '<td><strong>' + esc(s.service_name) + '</strong></td>' +
      '<td class="text-steel-400 dark:text-steel-500">' + esc(s.description || 'N/A') + '</td>' +
      '<td><ul class="m-0 list-none p-0 [&_li]:relative [&_li]:pl-[15px] [&_li]:text-xs [&_li]:leading-normal [&_li]:text-steel-500 [&_li]:dark:text-steel-400 [&_li]:before:absolute [&_li]:before:left-0 [&_li]:before:font-bold [&_li]:before:text-ok [&_li]:before:content-[\'✓\']">' +
        featureList(s.features).map((f) => '<li>' + esc(f) + '</li>').join('') + '</ul></td>' +
      '<td><div class="flex flex-wrap gap-1.5">' + specNames(s.specialties).map((n) =>
        '<span class="' + BADGE + ' bg-steel-100 text-steel-500 dark:bg-white/10 dark:text-steel-300">' + esc(n) + '</span>').join('') + '</div></td>' +
      '<td class="amt"><strong>' + priceLabel(s) + '</strong></td>' +
      '<td class="text-[13px] text-steel-500 dark:text-steel-300">' + (+s.duration_minutes > 0
        ? '<span class="whitespace-nowrap">' + esc(fmtDuration(s.duration_minutes).replace(/^about /, '')) + '</span>' +
          (s.quantity_unit ? '<span class="block text-[11px] text-steel-400 dark:text-steel-500">per ' + esc(s.quantity_unit) + '</span>' : '')
        : 'N/A') + '</td>' +
      '<td><span class="' + BADGE + ' bg-ok-soft text-ok dark:bg-ok/20">Active</span></td>' +
      '<td><div class="flex flex-wrap gap-2">' +
        '<button class="' + BTN_SM + GHOST + '" data-edit="' + s.service_id + '">Edit</button>' +
        '<button class="' + BTN_SM + WARN + '" data-toggle="' + s.service_id + '">Archive</button>' +
      '</div></td></tr>'
    ).join('') : EMPTY8 + 'No matching services.</td></tr>';
  }

  function form(s) {
    s = s || {};
    const picked = (s.specialties || '').split(',').filter(Boolean);
    return modalShell(s.service_id ? 'Edit Service' : 'Add New Service',
      '<div class="grid grid-cols-2 gap-3.5">' +
      '<div class="' + FG + '"><label class="' + LBL + '">Service Name</label><input class="' + INP + '" id="f_name" value="' + esc(s.service_name || '') + '"></div>' +
      '<div class="' + FG + '"><label class="' + LBL + '">Base Price (₱)</label><input class="' + INP + '" id="f_price" type="text" inputmode="decimal" data-money value="' + window.moneyStr(s.base_price || 0) + '"></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Usual Duration (minutes) <span class="text-steel-400 dark:text-steel-500 font-normal">(shown to patients; for a per-unit service, the time per unit)</span></label>' +
        '<input class="' + INP + '" id="f_duration" type="number" min="5" max="480" step="5" value="' + esc(s.duration_minutes || '30') + '"></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Charged Per <span class="text-steel-400 dark:text-steel-500 font-normal">(per-unit services let the dentist and cashier raise the quantity, e.g. 2 teeth)</span></label>' +
        '<select class="' + INP + '" id="f_qty_unit"><option value="">Whole visit (quantity stays 1)</option>' +
        QTY_UNITS.map((u) => '<option value="' + esc(u) + '"' + (s.quantity_unit === u ? ' selected' : '') + '>Per ' + esc(u) + '</option>').join('') +
        '</select></div>' +
      '<label class="col-span-2 flex items-start gap-2 text-[13px] font-medium text-navy-800 dark:text-steel-100"><input type="checkbox" id="f_inst" class="mt-0.5 accent-accent"' + (+s.allows_installment ? ' checked' : '') + '>' +
        '<span>Allow installment payments <span class="block text-xs font-normal text-steel-400 dark:text-steel-500">For orthodontic treatments such as braces. Bills without one of these services must be paid in full.</span></span></label>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Categories (specialties that perform this, select at least one)</label>' +
        '<div class="grid grid-cols-2 gap-2">' + Object.entries(SPECS).map(([k, label]) =>
          '<label class="flex items-center gap-2 font-medium"><input type="checkbox" class="f_spec accent-accent" value="' + k + '"' + (picked.includes(k) ? ' checked' : '') + '> ' + esc(label) + '</label>').join('') +
        '</div></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Service Image</label><input class="' + INP + '" type="file" id="f_img" accept="image/png,image/jpeg,image/webp">' +
        (s.image ? '<span class="text-[11px] text-steel-400 dark:text-steel-500">Current: ' + esc(s.image) + '</span>' : '') + '</div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Description</label><textarea class="' + INP + ' min-h-[72px] resize-y" id="f_desc">' + esc(s.description || '') + '</textarea></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Homepage Card Blurb <span class="text-steel-400 dark:text-steel-500 font-normal">(short, shown on the public landing page card; the full Description above shows in its detail popup)</span></label><input class="' + INP + '" id="f_short_desc" maxlength="180" value="' + esc(s.short_description || '') + '"></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Features (one per line)</label><textarea class="' + INP + ' resize-y" id="f_feat" rows="4" placeholder="e.g.\nNatural-looking results\nQuick curing process">' + esc(s.features || '') + '</textarea></div>' +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="f_save">' +
      (s.service_id ? 'Save Changes' : 'Add Service') + '</button>');
  }

  function openForm(s) {
    openModal(form(s));
    g('f_save').addEventListener('click', async () => {
      const btn = g('f_save');
      const specialties = [...document.querySelectorAll('.f_spec:checked')].map((c) => c.value);
      btn.disabled = true;
      try {
        let image = s ? s.image : '';
        const file = g('f_img').files[0];
        if (file) {
          const fd = new FormData();
          fd.append('image', file);
          const r = await fetch('/dgt_dental_clinic/api/services.php?action=upload_image', {
            method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd,
          });
          const up = await r.json();
          if (!r.ok || up.ok === false) throw new Error(up.error || 'Image upload failed.');
          image = up.image;
        }
        await api('services.php?action=save', { method: 'POST', body: {
          service_id: s ? s.service_id : 0,
          service_name: g('f_name').value.trim(),
          base_price: window.moneyVal(g('f_price')),
          quantity_unit: g('f_qty_unit').value,
          duration_minutes: g('f_duration').value,
          allows_installment: g('f_inst').checked,
          specialties,
          description: g('f_desc').value.trim(),
          short_description: g('f_short_desc').value.trim(),
          features: g('f_feat').value.trim(),
          image,
        }});
        closeModal(); toast('Service saved.'); load();
      } catch (e) { toast(e.message, 'err'); btn.disabled = false; }
    });
  }

  function archiveModal() {
    const archived = cache.filter((s) => s.is_active == 0);
    openModal(modalShell('Archived Services',
      archived.length
        ? '<div class="overflow-x-auto"><table class="w-full border-collapse text-[13px] [&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 [&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:dark:border-white/10 [&_.amt]:text-right"><thead><tr><th>Service</th><th>Categories</th><th class="amt">Price</th><th></th></tr></thead><tbody>' +
          archived.map((s) =>
            '<tr><td><strong>' + esc(s.service_name) + '</strong></td>' +
            '<td><div class="flex flex-wrap gap-1.5">' + specNames(s.specialties).map((n) => '<span class="' + BADGE + ' bg-steel-100 text-steel-500 dark:bg-white/10 dark:text-steel-300">' + esc(n) + '</span>').join('') + '</div></td>' +
            '<td class="amt">' + priceLabel(s) + '</td>' +
            '<td><button class="' + BTN_SM + PRIMARY + '" data-restore="' + s.service_id + '">Restore</button></td></tr>'
          ).join('') + '</tbody></table></div>'
        : '<p class="py-7 text-center text-steel-400 dark:text-steel-500">No archived services.</p>'), { wide: true });

    document.querySelector('#modalRoot .modal-body').addEventListener('click', async (e) => {
      const b = e.target.closest('[data-restore]');
      if (!b) return;
      try {
        await api('services.php?action=toggle', { method: 'POST', body: { service_id: +b.dataset.restore } });
        closeModal(); toast('Service restored.'); load();
      } catch (err) { toast(err.message, 'err'); }
    });
  }

  g('addServiceBtn').addEventListener('click', () => openForm(null));
  g('archiveServiceBtn').addEventListener('click', archiveModal);
  g('svcSearch').addEventListener('input', () => { PG.page = 1; render(); });
  g('svcCat').addEventListener('change', () => { PG.page = 1; render(); });
  window.fitOnResize(() => { if (document.getElementById('svcTable')) render(); });
  document.querySelector('#svcTable').addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-edit]');
    const tg = e.target.closest('[data-toggle]');
    if (ed) openForm(cache.find((s) => s.service_id == ed.dataset.edit));
    if (tg) {
      try { await api('services.php?action=toggle', { method: 'POST', body: { service_id: +tg.dataset.toggle } }); load(); }
      catch (err) { toast(err.message, 'err'); }
    }
  });

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
