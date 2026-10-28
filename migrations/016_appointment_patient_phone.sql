-- 016_appointment_patient_phone.sql
-- Per-booking patient contact number, captured on the patient's online Request
-- Appointment form (and available for receptionist walk-ins). Distinct from the
-- account holder's users.contact_number -- it matters most when the booking is
-- "for someone else".

ALTER TABLE appointments
    ADD COLUMN patient_phone VARCHAR(20) DEFAULT NULL AFTER patient_age;
