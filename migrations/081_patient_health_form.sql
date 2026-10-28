-- Patient Information Record (2026-10-20): a required health questionnaire
-- the patient answers before booking. The answers are kept as JSON and also
-- copied into the dental record (allergies, medical_history, emergency contact,
-- gender).
ALTER TABLE patient_records
  ADD COLUMN health_form TEXT DEFAULT NULL AFTER medical_history,
  ADD COLUMN health_form_at DATETIME DEFAULT NULL AFTER health_form;
