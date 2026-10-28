<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$me = apiAuth();
$action = $_GET['action'] ?? 'list';
$uid = (int) $me['user_id'];

switch ($action) {
    case 'count':
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$uid]);
        jsonOut(['ok' => true, 'count' => (int) $stmt->fetchColumn()]);

    case 'list':
        // The bell dropdown asks for a short list; the full Notifications page
        // passes ?all=1 for the complete history.
        $limit = isset($_GET['all']) ? 200 : 25;
        // A structured notification (migration 062) carries its appointment's
        // LIVE details, so a card always shows the current date/time/dentist and
        // knows whether its action buttons still apply (e.g. a declined
        // assignment that has since been reassigned).
        $staff = $me['role'] !== 'patient';
        $stmt = db()->prepare(
            "SELECT n.notification_id, n.appointment_id, n.type, n.title, n.message, n.is_read, n.created_at,
                    a.patient_name, a.scheduled_date, a.time_slot, a.status AS appt_status, a.dentist_id,
                    d.full_name AS dentist_name, sv.service_name,
                    " . ($staff ? 'a.checked_in_at, a.oncall_declined_by, a.oncall_declined_at, a.oncall_decline_reason, odb.full_name AS oncall_declined_by_name, a.sched_change_status'
                                : 'NULL AS checked_in_at, NULL AS oncall_declined_by, NULL AS oncall_declined_at, NULL AS oncall_decline_reason, NULL AS oncall_declined_by_name, NULL AS sched_change_status') . "
             FROM notifications n
             LEFT JOIN appointments a ON a.appointment_id = n.appointment_id
             LEFT JOIN users d ON d.user_id = a.dentist_id
             LEFT JOIN users odb ON odb.user_id = a.oncall_declined_by
             LEFT JOIN services sv ON sv.service_id = a.service_id
             WHERE n.user_id = ? ORDER BY n.created_at DESC, n.notification_id DESC LIMIT $limit"
        );
        $stmt->execute([$uid]);
        jsonOut(['ok' => true, 'notifications' => $stmt->fetchAll()]);

    case 'read_all':
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$uid]);
        jsonOut(['ok' => true]);

    case 'read_one':
        $id = (int) (requestBody()['notification_id'] ?? 0);
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        jsonOut(['ok' => true]);

    case 'delete':
        $id = (int) (requestBody()['notification_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM notifications WHERE notification_id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        jsonOut(['ok' => true]);

    case 'clear':
        $stmt = db()->prepare('DELETE FROM notifications WHERE user_id = ?');
        $stmt->execute([$uid]);
        jsonOut(['ok' => true]);

    default:
        jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
}
