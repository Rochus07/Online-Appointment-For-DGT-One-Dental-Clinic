-- ---------------------------------------------------------------------------
--  031 — Per-dentist working schedule (day-of-week + time-slot grid).
--
--  Advisor feedback: each dentist has a duty mode (On-Site Owner / Roaming
--  Associate) and a recurring weekly schedule — which days of the week and
--  which of the clinic's time slots they actually work. Online booking offers
--  only the on-site (Owner) dentist's active day+slot combinations; the
--  receptionist walk-in / approve / assign pickers only list dentists who are
--  on duty for the chosen date + time.
--
--  Duty mode already lives on users.doctor_type ENUM('owner','roaming')
--  (migration 011) — no column change, the admin Dentist Schedules page just
--  writes it now.
--
--  This supersedes the short-lived per-dentist leave-date approach — the
--  dentist_leave table (and its helper index) is dropped.
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\031_dentist_schedules.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

DROP TABLE IF EXISTS dentist_leave;

CREATE TABLE dentist_schedules (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dentist_id  INT NOT NULL,
    day_of_week TINYINT NOT NULL,          -- 0 = Sunday … 6 = Saturday (PHP date('w'); the clinic never opens Sunday)
    time_slot   VARCHAR(5) NOT NULL,       -- 'HH:MM', one of the clinic time_slots
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dentist_day_slot (dentist_id, day_of_week, time_slot),
    KEY idx_ds_lookup (day_of_week, time_slot, is_active),
    CONSTRAINT fk_ds_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed every current dentist with the full clinic schedule (Mon–Sat, every
-- seeded time slot) so booking behaviour is unchanged until an admin curates
-- it on the Dentist Schedules page. A dentist with zero rows is treated as
-- "works every clinic slot" by the app, so this backfill is belt-and-braces.
INSERT INTO dentist_schedules (dentist_id, day_of_week, time_slot)
SELECT u.user_id, d.dow, s.slot
FROM users u
JOIN (SELECT 1 AS dow UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) d
JOIN (SELECT '10:00' AS slot UNION SELECT '11:00' UNION SELECT '13:00'
      UNION SELECT '14:00' UNION SELECT '15:00' UNION SELECT '16:00' UNION SELECT '17:00') s
WHERE u.role = 'dentist' AND u.is_active = 1;
