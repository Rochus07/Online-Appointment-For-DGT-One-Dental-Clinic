-- 071: allergies the patient enters on My Profile (NULL = not answered yet,
-- "None" = answered, no allergies). The dentist sees it in Patient Details,
-- the appointment View and the Services Availed pop-up.
ALTER TABLE patient_records
  ADD COLUMN allergies VARCHAR(500) NULL AFTER emergency_contact_number;
