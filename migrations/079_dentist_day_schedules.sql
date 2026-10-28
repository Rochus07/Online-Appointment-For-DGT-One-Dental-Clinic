-- 079: per-date working hours for a dentist (2026-10-19).
-- The weekly grid (dentist_schedules) stays the usual pattern; a row here
-- replaces it for one exact date. slots = CSV of HH:MM ('' = off that day).

CREATE TABLE IF NOT EXISTS dentist_day_schedules (
    dentist_id  INT          NOT NULL,
    work_date   DATE         NOT NULL,
    slots       VARCHAR(255) NOT NULL DEFAULT '',
    updated_by  INT          DEFAULT NULL,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (dentist_id, work_date),
    CONSTRAINT fk_day_sched_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
