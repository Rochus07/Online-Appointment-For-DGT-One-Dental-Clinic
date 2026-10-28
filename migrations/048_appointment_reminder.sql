-- Tracks whether the day-before reminder notification has already been sent
-- for an appointment, so the best-effort sweep in api/appointments.php never
-- re-notifies the same patient on every subsequent page load.
ALTER TABLE appointments
    ADD COLUMN reminder_sent_at DATETIME NULL DEFAULT NULL AFTER checked_in_at;
