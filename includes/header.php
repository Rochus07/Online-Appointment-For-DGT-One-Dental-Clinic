<?php
/**
 * Top bar: greeting, live clock, and (patient only) the notifications bell.
 * The signed-in user's profile / logout lives in the sidebar footer.
 * Expects $me (current user array).
 */
declare(strict_types=1);

// Notifications are only used by the patient portal — plus one narrow,
// deliberate 2026-09 exception: a roaming/on-call dentist gets their own
// bell here too, scoped to just their on-call-assignment alerts (see
// api/appointments.php's notifyRoamingDentistAssigned() and the
// notifications-patient-only pinned memory for why this is an exception,
// not a precedent for every staff role).
$showBell = $me['role'] === 'patient';
$unread = 0;
if ($showBell) {
    try {
        $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$me['user_id']]);
        $unread = (int) $stmt->fetchColumn();
    } catch (Throwable) {
        // non-fatal
    }
}
$showOnCallBell = $me['role'] === 'dentist' && ($me['doctor_type'] ?? '') === 'roaming';
$oncallUnread = 0;
if ($showOnCallBell) {
    try {
        $stmt = getDB()->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND (type = 'oncall_assigned' OR title = 'New On-Call Assignment')"
        );
        $stmt->execute([$me['user_id']]);
        $oncallUnread = (int) $stmt->fetchColumn();
    } catch (Throwable) {
        // non-fatal
    }
}
// A third, equally narrow exception (2026-09): a dental assistant who was
// auto-assigned a checked-in patient (the front desk left the picker blank —
// see api/appointments.php's check_in action) gets their own bell too, scoped
// to just that one notification title, same pattern as the roaming-dentist
// bell above.
$showAssistantBell = $me['role'] === 'dental_assistant';
$asstUnread = 0;
if ($showAssistantBell) {
    try {
        $stmt = getDB()->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND title = 'New Patient Assigned'"
        );
        $stmt->execute([$me['user_id']]);
        $asstUnread = (int) $stmt->fetchColumn();
    } catch (Throwable) {
        // non-fatal
    }
}
// A fourth narrow exception (migration 062): the front desk is alerted when a
// roaming dentist declines an on-call assignment, scoped to that one type.
$showRcpBell = $me['role'] === 'receptionist';
$rcpUnread = 0;
if ($showRcpBell) {
    try {
        $stmt = getDB()->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND type IN ('oncall_declined','schedule_conflict')"
        );
        $stmt->execute([$me['user_id']]);
        $rcpUnread = (int) $stmt->fetchColumn();
    } catch (Throwable) {
        // non-fatal
    }
}
?>
<header id="topHeader" class="sticky top-0 z-30 flex h-[62px] items-center gap-3 border-b border-steel-100 bg-white px-4 sm:gap-4 sm:px-[22px] print:hidden dark:border-white/10 dark:bg-dgt-ink-800">
    <button id="menuToggle" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"
            class="-ml-2 grid h-11 w-11 flex-none cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-xl text-navy-700 nav:hidden dark:text-dgt-ink-100">&#9776;</button>

    <div class="flex min-w-0 flex-col">
        <?php /* Phones: the name gets the whole first line (it was cut off after
                 "Good evening, Rex C..."); the greeting is dropped there so the
                 name and the full day + time both fit. */ ?>
        <span class="line-clamp-2 break-words text-[15px] font-semibold leading-tight text-navy-800 sm:truncate sm:text-base sm:leading-normal dark:text-dgt-ink-50"><span class="hidden sm:inline"><?= e(greeting()) ?>, </span><?= e(shortName($me)) ?></span>
        <span class="text-xs tabular-nums text-steel-400" id="systemClock" data-clock>--:--:--</span>
    </div>

    <style>
        /* Notification bell — Uiverse.io/vinodjangid07, recoloured to DGT navy.
           A 40px navy circle; on hover the bell "rings" and on press it dips.
           Same navy-circle treatment as the theme toggle beside it, so both
           read as one pair of header controls regardless of light/dark. */
        #bellBtn, #oncallBellBtn, #asstBellBtn, #rcpBellBtn, #themeToggle { transition: background-color .3s ease, transform .12s ease; }
        #bellBtn:hover, #oncallBellBtn:hover, #asstBellBtn:hover, #rcpBellBtn:hover, #themeToggle:hover { background-color: #1c4a78; }
        #bellBtn:active, #oncallBellBtn:active, #asstBellBtn:active, #rcpBellBtn:active, #themeToggle:active { transform: scale(.85); }
        /* Dark mode: the navy circle nearly disappears against the dark-ink
           header background (#262e36 vs #102f51 — both very dark, low
           contrast). Swap to the brighter DGT blue accent so both controls
           stay visible instead of blending into the header. */
        html.dark #bellBtn, html.dark #oncallBellBtn, html.dark #asstBellBtn, html.dark #themeToggle { background-color: #006cc0; }
        html.dark #bellBtn:hover, html.dark #oncallBellBtn:hover, html.dark #asstBellBtn:hover, html.dark #themeToggle:hover { background-color: #2683d2; }
        .notif-bell:hover .bell-ico { animation: dgt-bell-ring .9s both; }
        @keyframes dgt-bell-ring {
            0%, 100% { transform: rotateZ(0); transform-origin: top; }
            15% { transform: rotateZ(10deg); }
            30% { transform: rotateZ(-10deg); }
            45% { transform: rotateZ(5deg); }
            60% { transform: rotateZ(-5deg); }
            75% { transform: rotateZ(2deg); }
        }
        @media (prefers-reduced-motion: reduce) {
            .notif-bell:hover .bell-ico { animation: none; }
            #bellBtn:active, #oncallBellBtn:active, #asstBellBtn:active, #rcpBellBtn:active, #themeToggle:active { transform: none; }
        }
        /* Mode icon: shows the CURRENT appearance (sun = Light, moon = Dark,
           monitor = Auto); the menu itself is assets/js/theme-menu.js. */
        #themeToggle .theme-ico { opacity: 0; transition: opacity .2s ease; }
        html[data-theme-mode="light"] #themeToggle .theme-ico-sun,
        html[data-theme-mode="dark"] #themeToggle .theme-ico-moon,
        html[data-theme-mode="system"] #themeToggle .theme-ico-auto { opacity: 1; }
    </style>
    <div class="ml-auto flex flex-none items-center gap-2.5">
        <button id="themeToggle" type="button" data-theme-menu aria-haspopup="dialog" aria-expanded="false" aria-label="Appearance" title="Appearance"
                class="relative grid h-10 w-10 cursor-pointer place-items-center rounded-full border-0 bg-[#102f51] text-white shadow-[2px_2px_10px_rgba(16,47,81,0.18)]">
            <svg class="theme-ico theme-ico-sun absolute inset-0 m-auto h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
            <svg class="theme-ico theme-ico-moon absolute inset-0 m-auto h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
            <svg class="theme-ico theme-ico-auto absolute inset-0 m-auto h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        </button>
        <?php if ($showBell): ?>
        <div class="relative" id="bellWrap">
            <button id="bellBtn" aria-label="Notifications"
                    class="notif-bell relative grid h-10 w-10 cursor-pointer place-items-center rounded-full border-0 bg-[#102f51] text-white shadow-[2px_2px_10px_rgba(16,47,81,0.18)]">
                <svg viewBox="0 0 448 512" class="bell-ico h-[18px] w-[18px] [&_path]:fill-white" aria-hidden="true"><path d="M224 0c-17.7 0-32 14.3-32 32V49.9C119.5 61.4 64 124.2 64 200v33.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V200c0-75.8-55.5-138.6-128-150.1V32c0-17.7-14.3-32-32-32zm0 96h8c57.4 0 104 46.6 104 104v33.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V200c0-57.4 46.6-104 104-104h8zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"/></svg>
                <span id="bellCount"
                      class="absolute -right-0.5 -top-0.5 grid h-4 min-w-[16px] place-items-center rounded-full bg-danger px-1 text-[10px] font-bold text-white<?= $unread === 0 ? ' hidden' : '' ?>"><?= $unread ?></span>
            </button>
            <div class="bell-panel absolute right-0 top-[calc(100%+8px)] z-50 min-w-[300px] overflow-hidden rounded-[10px] border border-steel-100 bg-white shadow-[0_6px_20px_rgba(15,28,46,0.12)] dark:border-white/10 dark:bg-dgt-ink-800"
                 id="bellPanel" hidden>
                <div class="flex items-center justify-between border-b border-steel-100 px-3.5 py-3 dark:border-white/10 dark:text-dgt-ink-50">
                    <strong>Notifications</strong>
                    <button class="tab cursor-pointer border-0 bg-transparent text-xs text-accent-dark outline-none hover:underline focus-visible:underline" id="markAllRead">Mark all read</button>
                </div>
                <div class="bell-list max-h-[340px] overflow-y-auto" id="bellList"><p class="p-3.5 text-steel-400">Loading&hellip;</p></div>
                <div class="border-t border-steel-100 px-3.5 py-2.5 text-center dark:border-white/10">
                    <a data-nav href="/dgt_dental_clinic/modules/patient/notifications"
                       class="text-xs font-semibold text-accent-dark">See all notifications</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($showOnCallBell): ?>
        <!-- Roaming-dentist on-call bell (2026-09) — a narrow, deliberate copy of
             the patient bell above, not a repurposing of it: separate ids so the
             two never share state, a dropdown scoped to just on-call-assignment
             notifications, and clicking an item scrolls to (or navigates to +
             scrolls to) the dentist dashboard's "On-Call Assignments" card
             instead of a notifications page — see assets/js/app.js. -->
        <div class="relative" id="oncallBellWrap">
            <button id="oncallBellBtn" aria-label="On-call assignment notifications"
                    class="notif-bell relative grid h-10 w-10 cursor-pointer place-items-center rounded-full border-0 bg-[#102f51] text-white shadow-[2px_2px_10px_rgba(16,47,81,0.18)]">
                <svg viewBox="0 0 448 512" class="bell-ico h-[18px] w-[18px] [&_path]:fill-white" aria-hidden="true"><path d="M224 0c-17.7 0-32 14.3-32 32V49.9C119.5 61.4 64 124.2 64 200v33.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V200c0-75.8-55.5-138.6-128-150.1V32c0-17.7-14.3-32-32-32zm0 96h8c57.4 0 104 46.6 104 104v33.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V200c0-57.4 46.6-104 104-104h8zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"/></svg>
                <span id="oncallBellCount"
                      class="absolute -right-0.5 -top-0.5 grid h-4 min-w-[16px] place-items-center rounded-full bg-danger px-1 text-[10px] font-bold text-white<?= $oncallUnread === 0 ? ' hidden' : '' ?>"><?= $oncallUnread ?></span>
            </button>
            <div class="bell-panel absolute right-0 top-[calc(100%+8px)] z-50 min-w-[320px] overflow-hidden rounded-[10px] border border-steel-100 bg-white shadow-[0_6px_20px_rgba(15,28,46,0.12)] dark:border-white/10 dark:bg-dgt-ink-800"
                 id="oncallBellPanel" hidden>
                <div class="flex items-center justify-between border-b border-steel-100 px-3.5 py-3 dark:border-white/10 dark:text-dgt-ink-50">
                    <strong>On-Call Assignments</strong>
                    <button class="tab cursor-pointer border-0 bg-transparent text-xs text-accent-dark outline-none hover:underline focus-visible:underline" id="oncallMarkAllRead">Mark all read</button>
                </div>
                <div class="bell-list max-h-[340px] overflow-y-auto" id="oncallBellList"><p class="p-3.5 text-steel-400">Loading&hellip;</p></div>
                <div class="border-t border-steel-100 px-3.5 py-2.5 text-center dark:border-white/10">
                    <a data-nav href="/dgt_dental_clinic/modules/dentist/notifications"
                       class="text-xs font-semibold text-accent-dark">See all notifications</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($showAssistantBell): ?>
        <!-- Dental-assistant "New Patient Assigned" bell (2026-09) — the third
             narrow, deliberate copy of the patient bell above: separate ids, a
             dropdown scoped to just this one auto-assignment notification
             title, and clicking an item scrolls to (or navigates to +
             scrolls to) the assistant's own dashboard "Today's Schedule" card
             instead of a notifications page — see assets/js/app.js. -->
        <div class="relative" id="asstBellWrap">
            <button id="asstBellBtn" aria-label="New patient assignment notifications"
                    class="notif-bell relative grid h-10 w-10 cursor-pointer place-items-center rounded-full border-0 bg-[#102f51] text-white shadow-[2px_2px_10px_rgba(16,47,81,0.18)]">
                <svg viewBox="0 0 448 512" class="bell-ico h-[18px] w-[18px] [&_path]:fill-white" aria-hidden="true"><path d="M224 0c-17.7 0-32 14.3-32 32V49.9C119.5 61.4 64 124.2 64 200v33.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V200c0-75.8-55.5-138.6-128-150.1V32c0-17.7-14.3-32-32-32zm0 96h8c57.4 0 104 46.6 104 104v33.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V200c0-57.4 46.6-104 104-104h8zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"/></svg>
                <span id="asstBellCount"
                      class="absolute -right-0.5 -top-0.5 grid h-4 min-w-[16px] place-items-center rounded-full bg-danger px-1 text-[10px] font-bold text-white<?= $asstUnread === 0 ? ' hidden' : '' ?>"><?= $asstUnread ?></span>
            </button>
            <div class="bell-panel absolute right-0 top-[calc(100%+8px)] z-50 min-w-[320px] overflow-hidden rounded-[10px] border border-steel-100 bg-white shadow-[0_6px_20px_rgba(15,28,46,0.12)] dark:border-white/10 dark:bg-dgt-ink-800"
                 id="asstBellPanel" hidden>
                <div class="flex items-center justify-between border-b border-steel-100 px-3.5 py-3 dark:border-white/10 dark:text-dgt-ink-50">
                    <strong>New Patient Assigned</strong>
                    <button class="tab cursor-pointer border-0 bg-transparent text-xs text-accent-dark outline-none hover:underline focus-visible:underline" id="asstMarkAllRead">Mark all read</button>
                </div>
                <div class="bell-list max-h-[340px] overflow-y-auto" id="asstBellList"><p class="p-3.5 text-steel-400">Loading&hellip;</p></div>
                <div class="border-t border-steel-100 px-3.5 py-2.5 text-center dark:border-white/10">
                    <a data-nav href="/dgt_dental_clinic/modules/dental_assistant/notifications"
                       class="text-xs font-semibold text-accent-dark">See all notifications</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($showRcpBell): ?>
        <div class="relative" id="rcpBellWrap">
            <button id="rcpBellBtn" aria-label="Front desk alerts"
                    class="notif-bell relative grid h-10 w-10 cursor-pointer place-items-center rounded-full border-0 bg-[#102f51] text-white shadow-[2px_2px_10px_rgba(16,47,81,0.18)]">
                <svg viewBox="0 0 448 512" class="bell-ico h-[18px] w-[18px] [&_path]:fill-white" aria-hidden="true"><path d="M224 0c-17.7 0-32 14.3-32 32V49.9C119.5 61.4 64 124.2 64 200v33.4c0 45.4-15.5 89.5-43.8 124.9L5.3 377c-5.8 7.2-6.9 17.1-2.9 25.4S14.8 416 24 416H424c9.2 0 17.6-5.3 21.6-13.6s2.9-18.2-2.9-25.4l-14.9-18.6C399.5 322.9 384 278.8 384 233.4V200c0-75.8-55.5-138.6-128-150.1V32c0-17.7-14.3-32-32-32zm0 96h8c57.4 0 104 46.6 104 104v33.4c0 47.9 13.9 94.6 39.7 134.6H72.3C98.1 328 112 281.3 112 233.4V200c0-57.4 46.6-104 104-104h8zm64 352H224 160c0 17 6.7 33.3 18.7 45.3s28.3 18.7 45.3 18.7s33.3-6.7 45.3-18.7s18.7-28.3 18.7-45.3z"/></svg>
                <span id="rcpBellCount"
                      class="absolute -right-0.5 -top-0.5 grid h-4 min-w-[16px] place-items-center rounded-full bg-danger px-1 text-[10px] font-bold text-white<?= $rcpUnread === 0 ? ' hidden' : '' ?>"><?= $rcpUnread ?></span>
            </button>
            <div class="bell-panel absolute right-0 top-[calc(100%+8px)] z-50 min-w-[320px] overflow-hidden rounded-[10px] border border-steel-100 bg-white shadow-[0_6px_20px_rgba(15,28,46,0.12)] dark:border-white/10 dark:bg-dgt-ink-800"
                 id="rcpBellPanel" hidden>
                <div class="flex items-center justify-between border-b border-steel-100 px-3.5 py-3 dark:border-white/10 dark:text-dgt-ink-50">
                    <strong>Front Desk Alerts</strong>
                    <button class="tab cursor-pointer border-0 bg-transparent text-xs text-accent-dark outline-none hover:underline focus-visible:underline" id="rcpMarkAllRead">Mark all read</button>
                </div>
                <div class="bell-list max-h-[340px] overflow-y-auto" id="rcpBellList"><p class="p-3.5 text-steel-400">Loading&hellip;</p></div>
                <div class="border-t border-steel-100 px-3.5 py-2.5 text-center dark:border-white/10">
                    <a data-nav href="/dgt_dental_clinic/modules/receptionist/notifications"
                       class="text-xs font-semibold text-accent-dark">See all notifications</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</header>
