-- 040: One-off dentist absences (sick day / emergency leave) on top of the
-- recurring weekly working grid (dentist_schedules, migration 031).
--
-- The weekly grid answers "which weekdays does this dentist normally work";
-- it has no way to say "they were scheduled today but are actually out."
-- dentist_absences is that single-date override: a row here means the dentist
-- is OFF duty on that date regardless of what the grid says. It is checked
-- inside dentistBookableSlots() (includes/functions.php), so every consumer of
-- dentistWorksOn()/dentistOnDuty() — the admin dashboard's Doctors on Duty
-- card, the receptionist's confirm/assign/reschedule duty checks, and the
-- online booking availability/slots calculation for the owner dentist — all
-- pick it up automatically with no extra wiring.
--
-- Managed from the admin Dentist Schedules dashboard drill-down
-- (api/schedules.php action=set_absence, admin only). A row here never
-- touches the recurring grid — clearing it (deleting the row) simply restores
-- whatever the grid already says for that weekday.

USE dental_clinic_system;

CREATE TABLE dentist_absences (
    absence_id   INT AUTO_INCREMENT PRIMARY KEY,
    dentist_id   INT NOT NULL,
    absence_date DATE NOT NULL,
    reason       VARCHAR(191) DEFAULT NULL,
    marked_by    INT DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dentist_absence_date (dentist_id, absence_date),
    KEY idx_absence_date (absence_date),
    CONSTRAINT fk_absence_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_absence_by      FOREIGN KEY (marked_by)  REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
