-- Remove the "no-show" appointment status. The clinic decided not to track
-- no-shows as a distinct state; a missed visit is just left scheduled or
-- cancelled with a reason. No existing rows use 'no_show'.
-- Safe to run once against an existing dental_clinic_system database.
USE dental_clinic_system;

UPDATE appointments SET status = 'cancelled' WHERE status = 'no_show';

ALTER TABLE appointments
    MODIFY COLUMN status
        ENUM('pending','scheduled','completed','cancelled','rescheduled')
        NOT NULL DEFAULT 'pending';
