-- Tracks whether the "you missed your appointment" follow-up notification has
-- already been sent, so the best-effort sweep in api/appointments.php never
-- re-notifies the same patient on every subsequent page load.
ALTER TABLE appointments
    ADD COLUMN missed_notified_at DATETIME NULL DEFAULT NULL AFTER reminder_sent_at;

-- Backfill: any already-overdue, never-checked-in confirmed appointment on an
-- existing install should not suddenly blast out a notification the first
-- time this sweep runs — only genuinely new occurrences from this point
-- forward should ever notify.
UPDATE appointments
   SET missed_notified_at = NOW()
 WHERE status IN ('approved', 'rescheduled')
   AND scheduled_date < CURDATE()
   AND checked_in_at IS NULL
   AND missed_notified_at IS NULL;
