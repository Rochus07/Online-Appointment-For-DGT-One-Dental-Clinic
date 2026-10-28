<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$me = apiAuth(['admin']);
$action = $_GET['action'] ?? 'metrics';
runAppointmentSweeps();   // Delayed / Standby + end-of-day No-Show (migration 088)

if ($action === 'metrics') {
    $pdo = db();
    $revenue = (float) $pdo->query(
        'SELECT COALESCE(SUM(amount_cash + amount_gcash + reservation_fee_paid), 0) FROM payments'
    )->fetchColumn();

    $count = static function (string $where) use ($pdo): int {
        return (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE $where")->fetchColumn();
    };

    jsonOut(['ok' => true, 'metrics' => [
        'revenue'     => $revenue,
        'today'       => $count("scheduled_date = CURDATE() AND status IN ('approved','rescheduled') AND checked_in_at IS NULL"),
        'completed'   => $count("status = 'completed'"),
        'pending'     => $count("status = 'pending'"),
        'rescheduled' => $count("status = 'rescheduled'"),
        'cancelled'   => $count("status = 'cancelled'"),
    ]]);
}

/* ----------------------------------------------------------------------------
 * Operational (top-of-dashboard) section — real-time clinic-floor metrics.
 * Polled every 30s by modules/admin/dashboard.php.
 * ------------------------------------------------------------------------- */
if ($action === 'operational') {
    $pdo = db();
    $scalar = static function (string $sql, array $params = []) use ($pdo): int {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    };

    // Today's Schedule = the EXPECTED QUEUE: today's approved / rescheduled
    // bookings the patient has NOT arrived for yet (checked_in_at IS NULL). The
    // moment the front desk checks a patient in, that row leaves this card and
    // becomes an "Ongoing" patient — the two panels never overlap. A completed
    // visit isn't here either (it's in Today's Revenue / History).
    $todayTotal = $scalar(
        "SELECT COUNT(*) FROM appointments
         WHERE scheduled_date = CURDATE() AND status IN ('approved','rescheduled')
           AND checked_in_at IS NULL"
    );
    $todayPrev = $scalar(
        "SELECT COUNT(*) FROM appointments
         WHERE scheduled_date = CURDATE() - INTERVAL 1 DAY AND status IN ('approved','rescheduled')
           AND checked_in_at IS NULL"
    );
    // "Ongoing Patients" = today's checked-in visits the cashier has NOT billed
    // yet. A patient enters this card at front-desk check-in and stays through
    // the dentist visit AND the trip to the cashier — dropping off once the
    // cashier processes ANY payment (a full settlement OR an installment
    // downpayment → payment_status 'fully_paid' or 'partially_paid'), or the
    // visit is cancelled. A 'completed' visit whose bill is still untouched
    // ('unpaid' / no payment row) stays on the card — the cashier hasn't billed
    // it yet.
    $ongoing = $scalar(
        "SELECT COUNT(*) FROM appointments a
         WHERE a.scheduled_date = CURDATE() AND a.checked_in_at IS NOT NULL
           AND a.status <> 'cancelled'
           AND NOT EXISTS (
             SELECT 1 FROM payments p
             WHERE p.appointment_id = a.appointment_id
               AND p.payment_status IN ('fully_paid', 'partially_paid')
           )"
    );

    // Pending requests, split by how the booking came in — both online requests
    // and walk-ins now enter the queue as 'pending' and wait for front-desk
    // approval, so both figures are live.
    $pend = $pdo->query(
        "SELECT booking_type, COUNT(*) AS n FROM appointments
         WHERE status = 'pending' GROUP BY booking_type"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $pendOnline = (int) ($pend['online'] ?? 0);
    $pendWalkin = (int) ($pend['walk_in'] ?? 0);

    // Rescheduled appointments — running count (matches History Logs).
    $rescheduled = $scalar("SELECT COUNT(*) FROM appointments WHERE status = 'rescheduled'");

    // Doctors on duty = active dentists whose weekly working schedule
    // (dentist_schedules) puts them in the clinic today. This is the roster of
    // who is physically working — NOT who is logged in, and NOT "has an
    // appointment booked today". A dentist with no schedule rows at all counts
    // as on duty (same "unconfigured = works every slot" fallback as booking).
    $today = date('Y-m-d');
    $dentistIds = $pdo->query("SELECT user_id FROM users WHERE role = 'dentist' AND is_active = 1")
        ->fetchAll(PDO::FETCH_COLUMN);
    $doctorsActive = count($dentistIds);
    $doctorsOnDuty = 0;
    foreach ($dentistIds as $did) {
        if (dentistWorksOn((int) $did, $today)) {
            $doctorsOnDuty++;
        }
    }

    // Dental assistants are non-account roster staff (see api/assistants.php).
    // "On duty" = a receptionist/admin marked them in for today (assistant_shifts).
    $assistantsActive = $scalar("SELECT COUNT(*) FROM dental_assistants WHERE is_active = 1");
    $assistantsOnDuty = $scalar(
        "SELECT COUNT(*) FROM assistant_shifts s
         JOIN dental_assistants da ON da.assistant_id = s.assistant_id AND da.is_active = 1
         WHERE s.shift_date = CURDATE()"
    );

    jsonOut(['ok' => true, 'operational' => [
        'today_total'        => $todayTotal,
        'today_prev'         => $todayPrev,
        'ongoing'            => $ongoing,
        'pending_total'      => $pendOnline + $pendWalkin,
        'pending_online'     => $pendOnline,
        'pending_walkin'     => $pendWalkin,
        'rescheduled'        => $rescheduled,
        'doctors_on_duty'    => $doctorsOnDuty,
        'doctors_active'     => $doctorsActive,
        'assistants_on_duty' => $assistantsOnDuty,
        'assistants_active'  => $assistantsActive,
    ]]);
}

/* Upcoming Appointments feed for the dashboard right column — the next confirmed
 * visits from today onward, with the patient's avatar for the row. Read-only. */
if ($action === 'upcoming') {
    $rows = db()->query(
        "SELECT a.appointment_id, a.patient_name, a.scheduled_date, a.time_slot, a.status,
                sv.service_name AS service, d.full_name AS dentist_name,
                u.avatar AS patient_avatar
         FROM appointments a
         LEFT JOIN users u  ON u.user_id = a.patient_id
         LEFT JOIN users d  ON d.user_id = a.dentist_id
         LEFT JOIN services sv ON sv.service_id = a.service_id
         WHERE a.status IN ('approved','rescheduled') AND a.scheduled_date >= CURDATE()
         ORDER BY a.scheduled_date ASC, a.time_slot ASC
         LIMIT 15"
    )->fetchAll();
    jsonOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'list') {
    $key = $_GET['key'] ?? '';
    $map = [
        'today'          => "a.scheduled_date = CURDATE() AND a.status IN ('approved','rescheduled') AND a.checked_in_at IS NULL",
        'today_schedule' => "a.scheduled_date = CURDATE() AND a.status IN ('approved','rescheduled') AND a.checked_in_at IS NULL",
        'ongoing'        => "a.scheduled_date = CURDATE() AND a.checked_in_at IS NOT NULL AND a.status <> 'cancelled'
                             AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.appointment_id = a.appointment_id AND p.payment_status IN ('fully_paid','partially_paid'))",
        'completed'      => "a.status = 'completed'",
        'pending'        => "a.status = 'pending'",
        'rescheduled'    => "a.status = 'rescheduled'",
        'cancelled'      => "a.status = 'cancelled'",
        'revenue'        => "pay.payment_id IS NOT NULL AND (pay.amount_cash + pay.amount_gcash + pay.reservation_fee_paid) > 0",
    ];
    if (!isset($map[$key])) {
        jsonOut(['ok' => false, 'error' => 'Unknown metric'], 400);
    }
    $sql = "SELECT a.appointment_id, a.patient_name, a.scheduled_date, a.time_slot, a.status,
                   a.checked_in_at, a.booking_type,
                   d.full_name AS dentist_name,
                   sv.service_name AS requested_service,
                   pay.total_amount, pay.remaining_balance, pay.payment_status,
                   (pay.amount_cash + pay.amount_gcash + pay.amount_bank + pay.reservation_fee_paid) AS collected
            FROM appointments a
            LEFT JOIN users d ON d.user_id = a.dentist_id
            LEFT JOIN services sv ON sv.service_id = a.service_id
            LEFT JOIN payments pay ON pay.appointment_id = a.appointment_id
            WHERE {$map[$key]}
            ORDER BY a.scheduled_date DESC, a.time_slot ASC";
    jsonOut(['ok' => true, 'rows' => db()->query($sql)->fetchAll()]);
}

/* Drill-down for the STAFF operational tiles (Doctors / Assistants on Duty,
 * Total Active Accounts) — a people list, not an appointment list. */
if ($action === 'staff_list') {
    $pdo = db();
    $key = $_GET['key'] ?? '';

    if ($key === 'doctors_on_duty') {
        // Every active dentist, flagged by whether their weekly working schedule
        // puts them in the clinic today (dentist_schedules) — the appointment
        // counts are extra context, not what "on duty" means.
        $today = date('Y-m-d');
        $appt = $pdo->query(
            "SELECT a.dentist_id,
                    COUNT(*)                                   AS today_count,
                    SUM(a.status = 'completed')                AS done_count,
                    SUM(a.status IN ('approved','rescheduled')) AS pending_count
             FROM appointments a
             WHERE a.scheduled_date = CURDATE()
               AND a.status IN ('approved','rescheduled','completed')
             GROUP BY a.dentist_id"
        )->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

        $dentists = $pdo->query(
            "SELECT u.user_id, u.full_name, u.role, u.doctor_type,
                    TIMESTAMPDIFF(SECOND, u.last_seen_at, NOW()) AS seen_ago
             FROM users u
             WHERE u.is_active = 1 AND u.role = 'dentist'
             ORDER BY u.full_name"
        )->fetchAll();

        $rows = [];
        foreach ($dentists as $d) {
            $id = (int) $d['user_id'];
            $a  = $appt[$d['user_id']] ?? [];
            // scheduled_today ignores a same-day absence override (the recurring grid alone);
            // on_duty is the real "physically in the clinic now" flag the card counts.
            $scheduledToday = dentistBookableSlots($id, $today, true) !== [];
            $absentToday    = dentistAbsentOn($id, $today);
            $rows[] = $d + [
                'on_duty'         => ($scheduledToday && !$absentToday) ? 1 : 0,
                'scheduled_today' => $scheduledToday ? 1 : 0,
                'absent_today'    => $absentToday ? 1 : 0,
                'today_count'     => (int) ($a['today_count'] ?? 0),
                'done_count'      => (int) ($a['done_count'] ?? 0),
                'pending_count'   => (int) ($a['pending_count'] ?? 0),
            ];
        }
        usort($rows, static fn ($x, $y) => ($y['on_duty'] <=> $x['on_duty']) ?: strcmp($x['full_name'], $y['full_name']));
    } elseif ($key === 'assistants_on_duty') {
        // Read-only roster of assistants currently on duty (an assistant
        // manages their own duty status from their own dashboard — this drill
        // is a snapshot, not a control). "Active patients" = today's visits
        // this assistant has checked in that aren't done yet (completed/
        // cancelled), the same definition the admin dashboard's own "Ongoing
        // Patients" card uses, just scoped to one assistant.
        $rows = $pdo->query(
            "SELECT da.assistant_id, da.full_name,
                    (SELECT COUNT(*) FROM appointments a
                     WHERE a.checkin_assistant_id = da.assistant_id
                       AND a.scheduled_date = CURDATE()
                       AND a.checked_in_at IS NOT NULL
                       AND a.status NOT IN ('completed', 'cancelled')) AS active_patients
             FROM dental_assistants da
             WHERE da.is_active = 1
               AND EXISTS (SELECT 1 FROM assistant_shifts s
                           WHERE s.assistant_id = da.assistant_id
                             AND s.shift_date = CURDATE())
             ORDER BY da.full_name"
        )->fetchAll();
    } else {
        jsonOut(['ok' => false, 'error' => 'Unknown roster'], 400);
    }

    foreach ($rows as &$r) {
        if (isset($r['role'])) {
            $r['role_label'] = roleLabel($r['role']);
        }
    }
    unset($r);

    jsonOut(['ok' => true, 'key' => $key, 'rows' => $rows]);
}

/* Revenue Trend line chart — the Revenue Summary card's only content now
 * (the three fixed today/week/month tiles it used to sit under, and their
 * own action=revenue_list drill-down, were removed 2026-09 at the user's
 * request). A per-day or per-month gross-revenue series, recognised on
 * COALESCE(DATE(paid_at), payment_date) settlement date and PHP clock
 * (paid_at is itself stamped via PHP's date() in recomputePayment() — see
 * the clock-skew note in CLAUDE.md; this
 * is a deliberate exception to the "always use the DB clock" rule that
 * everything else in this app follows, kept only for revenue windows so they
 * stay self-consistent with the timestamps they're summing).
 *   view=day (default): the current calendar month, day 1 through its real
 *     last day — no dropdown, matching Service Usage & Demand's own Daily
 *     view exactly (a "Last 7 Days" default + calendar-month dropdown was
 *     tried first and removed 2026-09 at the user's request for parity).
 *   view=month: rolling 12 calendar months ending with the current month. */
if ($action === 'revenue_summary') {
    $pdo   = db();
    $view  = (($_GET['view'] ?? 'day') === 'month') ? 'month' : 'day';
    $today = new DateTimeImmutable(date('Y-m-d'));

    if ($view === 'month') {
        $categories = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $today->modify("first day of -{$i} month");
            $categories[] = ['key' => $m->format('Y-m'), 'label' => $m->format('M Y'), 'tip_label' => $m->format('F Y')];
        }
        $from       = $categories[0]['key'] . '-01';
        $to         = $today->modify('last day of this month')->format('Y-m-d');
        $bucketExpr = "DATE_FORMAT(dt, '%Y-%m')";
    } else {
        $first = $today->modify('first day of this month');
        $last  = $today->modify('last day of this month');
        $categories = [];
        for ($cursor = $first; $cursor <= $last; $cursor = $cursor->modify('+1 day')) {
            $categories[] = ['key' => $cursor->format('Y-m-d'), 'label' => $cursor->format('M j'), 'tip_label' => $cursor->format('M j, Y')];
        }
        $from       = $first->format('Y-m-d');
        $to         = $last->format('Y-m-d');
        $bucketExpr = 'dt';
    }

    // $bucketExpr is one of the fixed literals above, never user input.
    $stmt = $pdo->prepare(
        "SELECT $bucketExpr AS bucket, COALESCE(SUM(amount), 0) AS revenue
         FROM (
             SELECT rev_date AS dt, amount FROM " . revenueLinesSql() . " rl
         ) t
         WHERE dt BETWEEN ? AND ?
         GROUP BY bucket"
    );
    $stmt->execute([$from, $to]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[$r['bucket']] = (float) $r['revenue'];
    }

    $points = [];
    $total  = 0.0;
    foreach ($categories as $c) {
        $v = $map[$c['key']] ?? 0.0;
        $points[] = ['key' => $c['key'], 'label' => $c['label'], 'tip_label' => $c['tip_label'], 'value' => $v];
        $total += $v;
    }

    jsonOut(['ok' => true, 'view' => $view, 'from' => $from, 'to' => $to, 'points' => $points, 'total' => $total]);
}

/* Drill-down behind a single point on the Revenue Summary line chart above —
 * the individual fully-paid transactions that make up that one (day-or-month)
 * revenue figure, so clicking a point behaves like every other clickable
 * panel on this dashboard (a bar, a tile, a donut arc opening a row-level
 * detail modal) — mirrors service_usage_detail's shape exactly, one bucket
 * over one signal instead of one (service, bucket) pair. Revenue windows stay
 * on the PHP clock here too (see the revenue_summary note above) so a bucket
 * boundary always lines up with what the summary chart itself just counted. */
if ($action === 'revenue_detail') {
    $pdo    = db();
    $view   = (($_GET['view'] ?? 'day') === 'month') ? 'month' : 'day';
    $bucket = (string) ($_GET['bucket'] ?? '');

    if ($view === 'month') {
        if (!preg_match('/^\d{4}-\d{2}$/', $bucket)) {
            jsonOut(['ok' => false, 'error' => 'Invalid bucket'], 422);
        }
        $from        = $bucket . '-01';
        $to          = (new DateTimeImmutable($from))->modify('last day of this month')->format('Y-m-d');
        $bucketLabel = (new DateTimeImmutable($from))->format('F Y');
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bucket)) {
            jsonOut(['ok' => false, 'error' => 'Invalid bucket'], 422);
        }
        $from = $to  = $bucket;
        $bucketLabel = (new DateTimeImmutable($bucket))->format('M j, Y');
    }

    // Same revenue-recognition rule as the summary aggregate above:
    // payment_status = 'fully_paid', COALESCE(DATE(paid_at), payment_date),
    // summed as total_amount - discount. Service falls back to a
    // GROUP_CONCAT of the availed procedures for a legacy walk-in with no
    // a.service_id, same convention as historyLogRows()/the revenue reports.
    // **The Date/Time returned here are the revenue-recognition date/time
    // (COALESCE(paid_at, payment_date)), never a.scheduled_date/time_slot** —
    // a visit can be scheduled one day and settled another (an installment
    // paid off later, or a same-day visit billed after midnight), so the row
    // must show *when this transaction counted as revenue*, matching exactly
    // what the clicked point's own bucket/title represent. Showing
    // scheduled_date here instead was a real bug: a payment settled on Sep 21
    // (correctly inside the Sep 21 bucket) displayed "Sep 20" because that
    // was the appointment's own visit date, reading as if the query had
    // filtered the wrong day when it had not — only the displayed column was
    // wrong. revenue_time is null (rendered as "N/A" client-side) when there's
    // no paid_at to take a time from, i.e. the row is on record only via the
    // date-only payment_date fallback column.
    $stmt = $pdo->prepare(
        "SELECT rl.appointment_id,
                rl.rev_date AS revenue_date,
                CASE WHEN rl.rev_at IS NOT NULL THEN DATE_FORMAT(rl.rev_at, '%h:%i %p') END AS revenue_time,
                a.patient_name,
                d.full_name AS dentist_name,
                COALESCE(sv.service_name, (
                    SELECT GROUP_CONCAT(s2.service_name SEPARATOR ', ')
                    FROM appointment_services aps2
                    JOIN services s2 ON s2.service_id = aps2.service_id
                    WHERE aps2.appointment_id = a.appointment_id
                )) AS requested_service,
                rl.kind, rl.amount
         FROM " . revenueLinesSql() . " rl
         JOIN appointments a ON a.appointment_id = rl.appointment_id
         LEFT JOIN users d ON d.user_id = a.dentist_id
         LEFT JOIN services sv ON sv.service_id = a.service_id
         WHERE rl.amount <> 0 AND rl.rev_date BETWEEN ? AND ?
         ORDER BY rl.rev_at, rl.payment_id"
    );
    $stmt->execute([$from, $to]);

    jsonOut(['ok' => true, 'bucket_label' => $bucketLabel, 'rows' => $stmt->fetchAll()]);
}

/* Service Usage & Demand — how many patients availed of each active service
 * ("availed" = the dentist logged it as a performed procedure on a completed
 * visit, appointment_services joined through a 'completed' appointment), as a
 * grouped/clustered series: one bucket per day (the current calendar month)
 * or per month (last 12 months), and one data series per active service so
 * the admin dashboard's bar graph can cluster every service's bars side by
 * side under each date/month and compare volume across the window at a
 * glance. Every active service is always its own series, even one that's all
 * zeros in this window, so the legend/colour never shifts as data changes.
 *
 * The Daily view briefly grew a "Last 7 Days / pick a calendar month"
 * dropdown (2026-09) so it could show either a rolling week or any past
 * month — removed the same day at the user's request in favour of one fixed
 * rule with no control to operate: Daily always means "this calendar month
 * so far," full stop. */
if ($action === 'service_usage') {
    $pdo  = db();
    $view = (($_GET['view'] ?? 'day') === 'month') ? 'month' : 'day';

    // "Today" comes from the DB clock, not PHP's date() — this app's PHP and
    // MySQL servers can disagree on timezone (see the dentalAssistantsOnDuty()
    // bug note in CLAUDE.md), so anchoring the rolling window to PHP's own
    // idea of "today" would silently drift by hours.
    $today = new DateTimeImmutable((string) $pdo->query('SELECT CURDATE()')->fetchColumn());

    if ($view === 'month') {
        // Last 12 calendar months, oldest first, ending with the current month.
        $categories = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $today->modify("first day of -{$i} month");
            $categories[] = ['key' => $m->format('Y-m'), 'label' => $m->format('M Y')];
        }
        $from       = $categories[0]['key'] . '-01';
        $to         = $today->modify('last day of this month')->format('Y-m-d');
        $bucketExpr = "DATE_FORMAT(a.scheduled_date, '%Y-%m')";
    } else {
        // The current calendar month, day 1 through its real last day
        // (28-31 categories depending on the month) — e.g. today in
        // September gives Sep 1..30.
        $first = $today->modify('first day of this month');
        $last  = $today->modify('last day of this month');
        $categories = [];
        for ($cursor = $first; $cursor <= $last; $cursor = $cursor->modify('+1 day')) {
            $categories[] = ['key' => $cursor->format('Y-m-d'), 'label' => $cursor->format('M j')];
        }
        $from       = $first->format('Y-m-d');
        $to         = $last->format('Y-m-d');
        $bucketExpr = 'a.scheduled_date';
        $rangeLabel = $first->format('F Y');
    }

    $services = $pdo->query(
        'SELECT service_id, service_name FROM services WHERE is_active = 1 ORDER BY service_name'
    )->fetchAll();

    // $bucketExpr is one of the fixed literals above, never user input.
    $stmt = $pdo->prepare(
        "SELECT aps.service_id, $bucketExpr AS bucket,
                COUNT(DISTINCT aps.appointment_id) AS n
         FROM appointment_services aps
         JOIN appointments a ON a.appointment_id = aps.appointment_id AND a.status = 'completed'
         WHERE a.scheduled_date BETWEEN ? AND ?
         GROUP BY aps.service_id, bucket"
    );
    $stmt->execute([$from, $to]);
    $counts = [];   // [service_id][bucket_key] = n
    foreach ($stmt->fetchAll() as $r) {
        $counts[(int) $r['service_id']][$r['bucket']] = (int) $r['n'];
    }

    // Every category (all 7 days, or all 28-31 days of the chosen month) gets
    // a real zero for a service with no completed visits there — never a gap
    // — so the grouped-bar structure and legend stay complete regardless of
    // demand, the same convention as the Monthly view above.
    $bucketKeys = array_column($categories, 'key');
    $series     = [];
    $total      = 0;
    foreach ($services as $s) {
        $sid  = (int) $s['service_id'];
        $data = [];
        foreach ($bucketKeys as $k) {
            $n = $counts[$sid][$k] ?? 0;
            $data[] = $n;
            $total += $n;
        }
        $series[] = ['service_id' => $sid, 'label' => $s['service_name'], 'data' => $data];
    }

    $out = ['ok' => true, 'view' => $view, 'from' => $from, 'to' => $to,
        'categories' => $categories, 'series' => $series, 'total_patients' => $total];
    if ($view === 'day') {
        $out['range_label'] = $rangeLabel;
    }
    jsonOut($out);
}

/* Drill-down behind a single bar in the Service Usage & Demand chart above —
 * the completed visits that make up that one (service, day-or-month) count,
 * so clicking a bar behaves like every other clickable panel on this
 * dashboard (a tile/donut click opening a row-level detail modal). */
if ($action === 'service_usage_detail') {
    $pdo       = db();
    $view      = (($_GET['view'] ?? 'day') === 'month') ? 'month' : 'day';
    $serviceId = (int) ($_GET['service_id'] ?? 0);
    $bucket    = (string) ($_GET['bucket'] ?? '');

    if ($serviceId <= 0 || $bucket === '') {
        jsonOut(['ok' => false, 'error' => 'Missing service_id or bucket'], 422);
    }

    if ($view === 'month') {
        if (!preg_match('/^\d{4}-\d{2}$/', $bucket)) {
            jsonOut(['ok' => false, 'error' => 'Invalid bucket'], 422);
        }
        $from        = $bucket . '-01';
        $to          = (new DateTimeImmutable($from))->modify('last day of this month')->format('Y-m-d');
        $bucketLabel = (new DateTimeImmutable($from))->format('F Y');
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bucket)) {
            jsonOut(['ok' => false, 'error' => 'Invalid bucket'], 422);
        }
        $from = $to  = $bucket;
        $bucketLabel = (new DateTimeImmutable($bucket))->format('M j, Y');
    }

    $svcStmt = $pdo->prepare('SELECT service_name FROM services WHERE service_id = ?');
    $svcStmt->execute([$serviceId]);
    $serviceName = $svcStmt->fetchColumn();
    if ($serviceName === false) {
        jsonOut(['ok' => false, 'error' => 'Unknown service'], 404);
    }

    $stmt = $pdo->prepare(
        "SELECT a.appointment_id, a.scheduled_date, a.time_slot, a.patient_name,
                d.full_name AS dentist_name
         FROM appointment_services aps
         JOIN appointments a ON a.appointment_id = aps.appointment_id AND a.status = 'completed'
         LEFT JOIN users d ON d.user_id = a.dentist_id
         WHERE aps.service_id = ? AND a.scheduled_date BETWEEN ? AND ?
         ORDER BY a.scheduled_date, a.time_slot"
    );
    $stmt->execute([$serviceId, $from, $to]);

    jsonOut(['ok' => true, 'service_name' => $serviceName, 'bucket_label' => $bucketLabel, 'rows' => $stmt->fetchAll()]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
