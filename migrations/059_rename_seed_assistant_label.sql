-- ============================================================
--  Display-label rename: "Dental Assistant" -> "Assistant Dentist".
--  Only the legacy no-login placeholder roster row (seeded by
--  migration 038/028) still carries the old name in the database;
--  every other occurrence of this label is in application code, not
--  data, and is fixed there instead. This migration only touches that
--  one placeholder row so it matches the new display convention.
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 dgtone_db < migrations\059_rename_seed_assistant_label.sql
-- ============================================================

UPDATE dental_assistants
SET first_name = 'Assistant', last_name = 'Dentist', full_name = 'Assistant Dentist'
WHERE first_name = 'Dental' AND last_name = 'Assistant';
