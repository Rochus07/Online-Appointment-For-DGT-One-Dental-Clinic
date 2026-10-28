-- 064: "No Show" appointment status (2026-10).
-- The receptionist marks an approved / rescheduled visit whose time has passed
-- without a check-in as 'no_show'. It closes the slot (every capacity query
-- only counts pending / approved / rescheduled) and keeps the row for history.
ALTER TABLE appointments
    MODIFY COLUMN status ENUM('pending','approved','completed','cancelled','rescheduled','payment_declined','no_show') NOT NULL DEFAULT 'pending';
