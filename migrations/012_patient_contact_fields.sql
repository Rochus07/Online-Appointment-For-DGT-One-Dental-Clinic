-- 012_patient_contact_fields.sql
-- Front-desk contact details on the patient record. The receptionist Patient
-- Records view can create patients and edit these fields plus users.contact_number
-- / users.email, but never sees the clinical dental chart, clinical text fields,
-- medical history or per-visit treatment notes (those stay dentist / admin only).

ALTER TABLE patient_records
    ADD COLUMN address                  VARCHAR(255) DEFAULT NULL AFTER gender,
    ADD COLUMN emergency_contact_name   VARCHAR(120) DEFAULT NULL AFTER address,
    ADD COLUMN emergency_contact_number VARCHAR(30)  DEFAULT NULL AFTER emergency_contact_name;
