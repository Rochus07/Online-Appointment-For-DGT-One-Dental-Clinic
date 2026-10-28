-- 072: staff date of birth (2026-10-17). Patients keep theirs in
-- patient_records.date_of_birth; every other role now stores it here so
-- My Profile can show an Age for everyone.
ALTER TABLE users ADD COLUMN date_of_birth DATE NULL DEFAULT NULL AFTER contact_number;
