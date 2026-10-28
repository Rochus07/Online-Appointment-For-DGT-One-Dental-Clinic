-- 011_doctor_type.sql
-- On-Demand Roaming Dentists. Tag every dentist account as either the on-site
-- Owner Dentist or an on-call Roaming / Associate Dentist. Owner and roaming
-- dentists share the exact same interface; differentiation is purely
-- WHERE dentist_id = :logged_in_user_id filtering plus this tag.
--
--   owner   - primary / on-site; receives all defaulted online bookings and
--             walk-ins, and is the only doctor type offered for direct online
--             booking.
--   roaming - on-call / associate; starts with an empty schedule until the
--             receptionist reassigns waiting patients to them live.

ALTER TABLE users
    ADD COLUMN doctor_type ENUM('owner','roaming') NOT NULL DEFAULT 'roaming' AFTER role;

-- Seed: Dr. Elaine Silva is the owner; the associate stays roaming (the default).
UPDATE users SET doctor_type = 'owner' WHERE username = 'dentist1';
