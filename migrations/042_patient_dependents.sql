-- Family-member ("someone else") records for online patient booking.
--
-- Before this migration, a patient booking "For Someone Else" only ever wrote
-- free-text patient_name/patient_age/relationship onto the appointment row —
-- the appointment itself, and any clinical chart the dentist later builds,
-- still hung off appointments.patient_id, i.e. the ACCOUNT HOLDER, not the
-- actual person treated. Two visits for two different family members under
-- one login ended up mixed into that one account's single patient_records row.
--
-- patient_dependents gives each family member their own real, persistent
-- clinical record — mirroring patient_records' clinical columns 1:1 so both
-- feed the same Patient Details modal shape on the dentist/receptionist
-- Patient Records pages — while still living "under" the account that manages
-- them (no login of their own, same non-account-entity pattern as
-- dental_assistants). A patient adds a family member once from their profile
-- or inline while booking, then reuses that same saved entry on every future
-- booking for that person, so their history accumulates on one record.
CREATE TABLE patient_dependents (
    dependent_id      INT AUTO_INCREMENT PRIMARY KEY,
    account_id        INT NOT NULL,                 -- the patient login that manages this dependent
    full_name         VARCHAR(120) NOT NULL,         -- auto-maintained mirror of first/middle/last, personName() convention
    first_name        VARCHAR(60)  NOT NULL DEFAULT '',
    middle_name       VARCHAR(60)  DEFAULT NULL,
    last_name         VARCHAR(60)  NOT NULL DEFAULT '',
    relationship      VARCHAR(60)  NOT NULL,         -- e.g. Parent, Child, Spouse, Guardian — same free-text as the old booking field
    date_of_birth     DATE DEFAULT NULL,
    gender            ENUM('male','female','other') DEFAULT NULL,
    contact_number    VARCHAR(30)  DEFAULT NULL,     -- optional override; the booking wizard falls back to the account's own number
    -- Clinical chart — same shape as patient_records so the dentist's Patient
    -- Details modal renders either kind of record identically.
    dental_age             VARCHAR(40)  DEFAULT NULL,
    occlusion              VARCHAR(120) DEFAULT NULL,
    periodontal_condition  VARCHAR(120) DEFAULT NULL,
    oral_hygiene           VARCHAR(120) DEFAULT NULL,
    denture_upper          VARCHAR(60)  DEFAULT NULL,
    denture_lower          VARCHAR(60)  DEFAULT NULL,
    abnormalities          VARCHAR(255) DEFAULT NULL,
    general_condition      TEXT DEFAULT NULL,
    medical_history        TEXT DEFAULT NULL,
    tooth_chart            LONGTEXT DEFAULT NULL,     -- JSON: { "11": "cured", ... }, same convention as patient_records
    is_active         TINYINT(1) NOT NULL DEFAULT 1,  -- archive instead of delete, same convention as users.is_active
    updated_by        INT DEFAULT NULL,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dep_account FOREIGN KEY (account_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_dep_by      FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_dependents_account ON patient_dependents(account_id);

-- The appointment's booking account (appointments.patient_id) never changes —
-- that is still "which login owns/can view/cancel/reschedule this booking".
-- dependent_id is the new, separate pointer to "who was actually seen", set
-- only when booking_for = 'other' and a saved family member was picked.
ALTER TABLE appointments
    ADD COLUMN dependent_id INT DEFAULT NULL AFTER patient_id,
    ADD CONSTRAINT fk_appt_dependent FOREIGN KEY (dependent_id) REFERENCES patient_dependents(dependent_id) ON DELETE SET NULL;

CREATE INDEX idx_appointments_dependent ON appointments(dependent_id);
