-- ============================================================
--  Dental assistants can have a real login account.
--  Migration 038 made assistants roster-only (no users row). This
--  adds an optional link from a roster row to a users row with
--  role = 'dental_assistant', so an assistant can sign in to look up
--  patient records (non-clinical view), check patients in, and
--  toggle their own duty status. Additive only: assistant_shifts and
--  appointments.checkin_assistant_id / treatment_assistant_id keep
--  pointing at dental_assistants.assistant_id, unchanged.
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 dgtone_db < migrations\058_dental_assistant_login.sql
-- ============================================================

ALTER TABLE dental_assistants
    ADD COLUMN user_id INT NULL AFTER assistant_id,
    ADD UNIQUE KEY uq_dental_assistants_user (user_id),
    ADD CONSTRAINT fk_dental_assistants_user
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;
