-- 010_patient_record_dental_age.sql
-- The Patient Details modal's clinical panel has a free-text "Child / Adult Age"
-- field (reference Figure 4.6b) that is separate from the derived age. Store it
-- on the record.

ALTER TABLE patient_records
    ADD COLUMN dental_age VARCHAR(40) DEFAULT NULL AFTER date_of_birth;
