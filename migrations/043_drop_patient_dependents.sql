-- Reverts migration 042's separate "family member" table (2026-09). The user
-- asked instead for a "for someone else" booking to just create a real patient
-- record for that person, the same way a walk-in's "New patient" flow does —
-- no separate family-relationship table at all. Repeat bookings for the same
-- person are now offered from that account's own past "for someone else"
-- appointments (api/appointments.php action=for_others), not a saved list.
--
-- No retroactive migration of existing rows: any appointment already pointing
-- at a patient_dependents row loses that link (set to NULL below) but keeps
-- its own patient_name / patient_age / relationship exactly as before — same
-- "leave existing bookings as-is" policy migration 042 itself was built under.

ALTER TABLE appointments DROP FOREIGN KEY fk_appt_dependent;

-- The live index was created as idx_appointments_dependent by migration 042
-- (schema.sql's own text drifted to idx_appt_dependent — a pre-existing
-- mismatch, fixed here rather than propagated further).
DROP INDEX idx_appointments_dependent ON appointments;

UPDATE appointments SET dependent_id = NULL;

ALTER TABLE appointments CHANGE dependent_id treated_patient_id INT DEFAULT NULL
    COMMENT 'set when booking_for=''other''; the real users.user_id of the person actually treated (a separate account from patient_id, the booking account) — 2026-09, replaces migration 042''s patient_dependents link';

ALTER TABLE appointments
    ADD CONSTRAINT fk_appt_treated_patient FOREIGN KEY (treated_patient_id) REFERENCES users(user_id) ON DELETE SET NULL;

CREATE INDEX idx_appt_treated_patient ON appointments(treated_patient_id);

DROP TABLE patient_dependents;
