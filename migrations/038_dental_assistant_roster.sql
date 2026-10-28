-- 038: Dental assistants become NON-ACCOUNT staff.
--
-- A dental assistant no longer has a `users` row / username / password / login.
-- They are a plain roster entry (dental_assistants), their daily presence is a
-- receptionist/admin "duty check-in" (assistant_shifts), and their work is tied
-- to a visit by two optional tags on `appointments`:
--   checkin_assistant_id   - set by the front-desk check-in modal
--   treatment_assistant_id - set by the dentist's Log Procedure modal
-- This supports task auditing, shift reporting and workload tracking with zero
-- login credentials.
--
-- Physical duties (for context): room preparation, instrument sterilization,
-- four-handed dentistry assistance (instrument passing, suction), material prep.

USE dental_clinic_system;

CREATE TABLE dental_assistants (
    assistant_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(120) NOT NULL,
    avatar       VARCHAR(255) DEFAULT NULL,          -- reserved; UI renders initials for now
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE assistant_shifts (                       -- one row = "on duty that day"
    id           INT AUTO_INCREMENT PRIMARY KEY,
    assistant_id INT NOT NULL,
    shift_date   DATE NOT NULL,
    marked_by    INT DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assistant_day (assistant_id, shift_date),
    KEY idx_shift_date (shift_date),
    CONSTRAINT fk_shift_assistant FOREIGN KEY (assistant_id) REFERENCES dental_assistants(assistant_id) ON DELETE CASCADE,
    CONSTRAINT fk_shift_by        FOREIGN KEY (marked_by)    REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE appointments
  ADD COLUMN checkin_assistant_id   INT DEFAULT NULL AFTER checked_in_at,
  ADD COLUMN treatment_assistant_id INT DEFAULT NULL AFTER checkin_assistant_id,
  ADD CONSTRAINT fk_appt_checkin_asst FOREIGN KEY (checkin_assistant_id)   REFERENCES dental_assistants(assistant_id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_appt_treat_asst   FOREIGN KEY (treatment_assistant_id) REFERENCES dental_assistants(assistant_id) ON DELETE SET NULL;

-- Migrate the seeded "Dental Assistant" into the roster, then drop the login.
-- (activity_log.user_id FK is ON DELETE SET NULL, so audit history survives.)
INSERT INTO dental_assistants (full_name)
SELECT full_name FROM users WHERE username = 'dental.assistant';

DELETE FROM users WHERE username = 'dental.assistant';
