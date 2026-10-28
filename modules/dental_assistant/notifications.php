<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['dental_assistant']);
$PAGE_TITLE = 'Patient Assignments';
$ACTIVE = '';
$PAGE_FULL = true;   // full-bleed content column (matches the patient page)

$btnGhostSm  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
$btnDangerSm = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md bg-danger px-2.5 py-1.5 text-xs font-semibold text-white hover:brightness-95';
$tableCls    = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10';

ob_start();
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Patient Assignments</h1></div>
    <div class="flex flex-wrap gap-2">
        <button class="<?= $btnGhostSm ?>" id="markAllBtn">Mark all read</button>
        <button class="<?= $btnDangerSm ?>" id="clearBtn">Clear all</button>
    </div>
</div>

<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>" id="notifTable">
            <thead><tr>
                <th class="w-[60%]">Notification</th><th>Received</th><th>Status</th><th></th>
            </tr></thead>
            <tbody><tr><td colspan="4" class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const tb = document.querySelector('#notifTable tbody');
  // Scoped to patient-assignment alerts only — same title the header bell
  // itself filters on — so this page reads as "Patient Assignments", not a
  // generic notifications inbox, even if some other notify() ever targets a
  // dentist later.
  const ASST_TITLE = 'New Patient Assigned';

  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold';
  const DANGER  = ' bg-danger text-white hover:brightness-95';
  const BADGE   = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const EMPTY   = 'py-7 text-center text-steel-400 dark:text-steel-500';

  function fmt(ts) {
    const d = new Date(String(ts).replace(' ', 'T'));
    return isNaN(d) ? esc(ts) : d.toLocaleString('en-US', {
      month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
    });
  }

  async function load() {
    try {
      const { notifications } = await api('notifications.php?action=list&all=1');
      const rows = notifications.filter((n) => n.title === ASST_TITLE);
      tb.innerHTML = rows.length ? rows.map((n) => {
        window.notifRemember(n);
        const unread = n.is_read == 0;
        // Clicking a row opens the details pop-up (data-nc-open, app.js).
        return '<tr class="cursor-pointer hover:bg-steel-50 dark:hover:bg-white/5' + (unread ? ' bg-accent-soft dark:bg-accent/20' : '') + '" data-nc-open="' + n.notification_id + '">' +
          '<td><strong>' + esc(n.title) + '</strong>' +
          '<div class="max-w-[640px] whitespace-normal text-xs text-steel-400 dark:text-steel-500">' + esc(window.notifBrief(n)) + '</div></td>' +
          '<td class="whitespace-nowrap text-xs text-steel-400 dark:text-steel-500">' + fmt(n.created_at) + '</td>' +
          '<td>' + (unread
            ? '<span class="' + BADGE + ' bg-warn-soft text-warn dark:bg-warn/20">Unread</span>'
            : '<span class="' + BADGE + ' bg-ok-soft text-ok dark:bg-ok/20">Read</span>') + '</td>' +
          '<td><div class="flex flex-wrap gap-2">' +
          '<button class="' + BTN_SM + DANGER + '" data-del="' + n.notification_id + '">Delete</button>' +
          '</div></td></tr>';
      }).join('') : '<tr><td colspan="4" class="' + EMPTY + '">No patient assignments yet.</td></tr>';
    } catch (e) {
      tb.innerHTML = '<tr><td colspan="4" class="' + EMPTY + '">' + esc(e.message) + '</td></tr>';
    }
  }

  tb.addEventListener('click', async (e) => {
    const del = e.target.closest('[data-del]');
    if (del) e.stopPropagation();   // Delete must not also open the pop-up
    try {
      if (del) {
        await api('notifications.php?action=delete', { method: 'POST', body: { notification_id: +del.dataset.del } });
      } else { return; }
      load();
      window.refreshAsstBellCount && window.refreshAsstBellCount();
    } catch (err) { toast(err.message, 'err'); }
  });

  g('markAllBtn').addEventListener('click', async () => {
    try {
      // read_all marks every notification for this account, but an assistant
      // only ever receives assignment ones, so this stays correct
      // without needing a title-scoped API action.
      await api('notifications.php?action=read_all', { method: 'POST', body: {} });
      toast('All notifications marked read.');
      load();
      window.refreshAsstBellCount && window.refreshAsstBellCount();
    } catch (err) { toast(err.message, 'err'); }
  });

  g('clearBtn').addEventListener('click', async () => {
    if (!confirm('Delete all of your patient assignment notifications? This cannot be undone.')) return;
    try {
      await api('notifications.php?action=clear', { method: 'POST', body: {} });
      toast('Notifications cleared.');
      load();
      window.refreshAsstBellCount && window.refreshAsstBellCount();
    } catch (err) { toast(err.message, 'err'); }
  });

  // Opening a pop-up marks the row read; reload so its status updates.
  window.__onNotifChanged = () => { if (document.body.contains(tb)) load(); };

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
