-- ============================================================
--  Pre-treatment verification stage for the Assistant Dentist role
--  (2026-09, refined workflow requirements). Parallels checked_in_at
--  exactly: a nullable timestamp, not a new appointments.status value —
--  this project deliberately never adds a synthetic pipeline stage into
--  the status ENUM (an earlier "Ongoing" status was built and reverted
--  for exactly this reason; see CLAUDE.md's Core Workflow section).
--  ready_notes is an optional free-text prep note the assistant leaves
--  when marking a patient ready ("Room 2 prepped, patient briefed").
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 dental_clinic_system < migrations\060_assistant_ready_for_treatment.sql
-- ============================================================

ALTER TABLE appointments
  ADD COLUMN ready_for_treatment_at DATETIME DEFAULT NULL AFTER checked_in_at,
  ADD COLUMN ready_notes VARCHAR(255) DEFAULT NULL AFTER ready_for_treatment_at;

CREATE INDEX idx_appt_ready ON appointments(ready_for_treatment_at);
