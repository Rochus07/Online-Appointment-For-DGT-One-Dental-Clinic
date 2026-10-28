-- 027_appointment_status_standardize.sql
-- Standardize the appointment status vocabulary across every role:
--   rename the enum member 'scheduled' -> 'approved' (MySQL cannot rename an
--   enum value in place, so: widen the enum, backfill, then narrow it), and
--   add a front-desk check-in timestamp that powers the "Ongoing Patients"
--   dashboard card (checked in = physically here / in treatment now).

ALTER TABLE appointments MODIFY COLUMN status
    ENUM('pending','scheduled','approved','completed','cancelled','rescheduled') NOT NULL DEFAULT 'pending';

UPDATE appointments SET status = 'approved' WHERE status = 'scheduled';

ALTER TABLE appointments MODIFY COLUMN status
    ENUM('pending','approved','completed','cancelled','rescheduled') NOT NULL DEFAULT 'pending';

ALTER TABLE appointments ADD COLUMN checked_in_at DATETIME DEFAULT NULL AFTER status;
CREATE INDEX idx_appt_checked_in ON appointments(checked_in_at);
