<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

// Front-desk alert center (migration 062): every on-call assignment a roaming
// dentist declined, with Reschedule / Reassign on each card. Reached from the
// header bell's "See all notifications" link, not the sidebar.
$me = checkAuth(['receptionist']);
$PAGE_TITLE = 'Front Desk Alerts';
$ACTIVE = '';
$PAGE_FULL = true;

$btnGhostSm  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
$btnDangerSm = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md bg-danger px-2.5 py-1.5 text-xs font-semibold text-white hover:brightness-95';

ob_start();
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50"><?= e($PAGE_TITLE) ?></h1></div>
    <div class="flex flex-wrap gap-2">
        <button class="<?= $btnGhostSm ?>" id="markAllBtn">Mark all read</button>
        <button class="<?= $btnDangerSm ?>" id="clearBtn">Clear all</button>
    </div>
</div>

<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-[13px] [&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 [&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10" id="notifTable">
            <thead><tr>
                <th class="w-[60%]">Notification</th><th>Received</th><th>Status</th><th></th>
            </tr></thead>
            <tbody id="ncList"><tr><td colspan="4" class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const list = g('ncList');
  const MATCH = (n) => n.type === 'oncall_declined' || n.type === 'schedule_conflict';
  const EMPTY_TXT = 'No alerts. Every patient has a dentist and a time.';
  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold';
  const GHOST   = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const DANGER  = ' bg-danger text-white hover:brightness-95';
  const BADGE   = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const EMPTY   = 'py-7 text-center text-steel-400 dark:text-steel-500';

  function fmt(ts) {
    const d = new Date(String(ts).replace(' ', 'T'));
    return isNaN(d) ? esc(ts) : d.toLocaleString('en-US', {
      month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
    });
  }

  // Same table as the patient Notifications page; clicking a row shows the pop-up with the details and the action buttons.
  async function load() {
    try {
      const { notifications } = await api('notifications.php?action=list&all=1');
      const all = notifications.filter(MATCH);
      const latest = window.notifLatestIds(all);
      const rows = window.notifSortOpenFirst(all, latest);   // still-waiting ones on top
      list.innerHTML = rows.length ? rows.map((n) => {
        window.notifRemember(n, latest.has(+n.notification_id));
        const unread = n.is_read == 0;
        return '<tr class="cursor-pointer hover:bg-steel-50 dark:hover:bg-white/5' + (unread ? ' bg-accent-soft dark:bg-accent/20' : '') + '" data-nc-open="' + n.notification_id + '">' +
          '<td><strong>' + esc(n.title) + '</strong>' +
          '<div class="max-w-[640px] whitespace-normal text-xs text-steel-400 dark:text-steel-500">' + esc(window.notifBrief(n)) + '</div>' +
          (window.notifStatus(n, latest.has(+n.notification_id)) ? '<div class="mt-0.5 text-xs font-medium text-dgt-ink-500 dark:text-dgt-ink-400">' + esc(window.notifStatus(n, latest.has(+n.notification_id))) + '</div>' : '') + '</td>' +
          '<td class="whitespace-nowrap text-xs text-steel-400 dark:text-steel-500">' + fmt(n.created_at) + '</td>' +
          '<td>' + (unread
            ? '<span class="' + BADGE + ' bg-warn-soft text-warn dark:bg-warn/20">Unread</span>'
            : '<span class="' + BADGE + ' bg-ok-soft text-ok dark:bg-ok/20">Read</span>') + '</td>' +
          '<td><div class="flex flex-wrap gap-2">' +
          '<button class="' + BTN_SM + DANGER + '" data-del="' + n.notification_id + '">Delete</button>' +
          '</div></td></tr>';
      }).join('') : '<tr><td colspan="4" class="' + EMPTY + '">' + esc(EMPTY_TXT) + '</td></tr>';
    } catch (e) {
      list.innerHTML = '<tr><td colspan="4" class="' + EMPTY + '">' + esc(e.message) + '</td></tr>';
    }
  }
  // A Decline / Reschedule / Reassign from the bell or a card reloads this list.
  window.__onNotifChanged = () => { if (document.body.contains(list)) load(); };

  list.addEventListener('click', async (e) => {
    const del = e.target.closest('[data-del]');
    if (del) e.stopPropagation();   // Delete must not also open the pop-up
    try {
      if (del) {
        await api('notifications.php?action=delete', { method: 'POST', body: { notification_id: +del.dataset.del } });
      } else { return; }
      window.notifChanged();
    } catch (err) { toast(err.message, 'err'); }
  });

  g('markAllBtn').addEventListener('click', async () => {
    try {
      await api('notifications.php?action=read_all', { method: 'POST', body: {} });
      toast('All notifications marked read.');
      window.notifChanged();
    } catch (err) { toast(err.message, 'err'); }
  });

  g('clearBtn').addEventListener('click', async () => {
    if (!confirm('Delete all of these notifications? This cannot be undone.')) return;
    try {
      await api('notifications.php?action=clear', { method: 'POST', body: {} });
      toast('Notifications cleared.');
      window.notifChanged();
    } catch (err) { toast(err.message, 'err'); }
  });

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
