-- Migration 066: Treatment Plan & Follow-up Service (2026-10-08).
-- When a dentist completes a visit they may recommend a follow-up service and a
-- timeframe. Each recommendation is one treatment_plans row, hard-locked to the
-- dentist who logged the visit. The patient books it from the Appointments page
-- ("Recommended Next Services"); that booking carries appointments.treatment_plan_id
-- and keeps the assigned dentist.

CREATE TABLE IF NOT EXISTS treatment_plans (
    plan_id                  INT AUTO_INCREMENT PRIMARY KEY,
    patient_id               INT NOT NULL,            -- booking account (sees + books the plan)
    treated_patient_id       INT DEFAULT NULL,        -- the real person treated when the visit was booked "for someone else"
    consultation_id          INT NOT NULL,            -- appointments.appointment_id of the visit that recommended it
    service_id               INT NOT NULL,
    assigned_dentist_id      INT NOT NULL,            -- locked: the dentist who logged the visit
    timeframe_recommendation VARCHAR(20) NOT NULL,    -- '1 Week' | '2 Weeks' | '1 Month' | '3 Months' | '6 Months'
    due_date                 DATE NOT NULL,           -- visit date + timeframe
    status                   ENUM('Pending','Scheduled','Completed') NOT NULL DEFAULT 'Pending',
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tp_patient      FOREIGN KEY (patient_id)          REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_treated      FOREIGN KEY (treated_patient_id)  REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_tp_consultation FOREIGN KEY (consultation_id)     REFERENCES appointments(appointment_id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_service      FOREIGN KEY (service_id)          REFERENCES services(service_id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_dentist      FOREIGN KEY (assigned_dentist_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_tp_consultation (consultation_id),
    KEY idx_tp_patient_status (patient_id, status)
) ENGINE=InnoDB;

ALTER TABLE appointments
    ADD COLUMN treatment_plan_id INT DEFAULT NULL AFTER service_id,
    ADD CONSTRAINT fk_appt_treatment_plan FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plans(plan_id) ON DELETE SET NULL;
