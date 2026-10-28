-- ---------------------------------------------------------------------------
--  029 — Staff activity / audit-trail log.
--
--  Backs the admin-only "Activity Log" page (modules/admin/logs.php), which was
--  converted from the old appointment History Logs into a dedicated system
--  audit trail. Appointment listings now live in modules/admin/appointments.php
--  and never mix into this table.
--
--  Rows are written by logActivity() in includes/functions.php from the auth,
--  appointment, patient-record and billing endpoints. username / role are
--  SNAPSHOT at write time so the log stays readable after an account is renamed
--  or archived (user_id also nulls out on account delete via the FK).
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\029_activity_log.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

CREATE TABLE IF NOT EXISTS activity_log (
    log_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id    INT             DEFAULT NULL,
    username   VARCHAR(60)     NOT NULL DEFAULT '',   -- snapshot of the actor's first.last
    role       VARCHAR(30)     NOT NULL DEFAULT '',   -- snapshot: admin / dentist / receptionist / cashier / ...
    category   VARCHAR(20)     NOT NULL,              -- auth | appointments | records | billing
    action     VARCHAR(255)    NOT NULL,             -- human-readable description
    ip         VARCHAR(45)     DEFAULT NULL,
    PRIMARY KEY (log_id),
    KEY idx_activity_created  (created_at),
    KEY idx_activity_category (category),
    KEY idx_activity_role     (role),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
