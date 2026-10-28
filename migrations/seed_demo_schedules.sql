-- ---------------------------------------------------------------------------
--  Demo data — realistic dentist duty modes + working schedules so the admin
--  "Dentist Schedules" page (modules/admin/schedules.php) and the on-duty
--  filtering on the booking / front-desk pickers have something to show.
--
--  IDEMPOTENT (not marker-based): it targets the two demo dentists by username
--  and fully rewrites their schedule rows, so re-running just resets them.
--
--    elaine.silva      — On-Site (Owner), full clinic grid (Mon–Sat, all slots)
--    associate.dentist — Roaming (Associate), Tue / Thu / Sat afternoons only
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\seed_demo_schedules.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

UPDATE users SET doctor_type = 'owner'   WHERE username = 'elaine.silva';
UPDATE users SET doctor_type = 'roaming' WHERE username = 'associate.dentist';

DELETE FROM dentist_schedules
 WHERE dentist_id IN (SELECT user_id FROM users WHERE username IN ('elaine.silva', 'associate.dentist'));

-- Owner: every clinic day + slot.
INSERT INTO dentist_schedules (dentist_id, day_of_week, time_slot)
SELECT u.user_id, d.dow, s.slot
FROM users u
JOIN (SELECT 1 AS dow UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) d
JOIN (SELECT '10:00' AS slot UNION SELECT '11:00' UNION SELECT '13:00'
      UNION SELECT '14:00' UNION SELECT '15:00' UNION SELECT '16:00' UNION SELECT '17:00') s
WHERE u.username = 'elaine.silva';

-- Associate: Tuesday / Thursday / Saturday afternoons.
INSERT INTO dentist_schedules (dentist_id, day_of_week, time_slot)
SELECT u.user_id, d.dow, s.slot
FROM users u
JOIN (SELECT 2 AS dow UNION SELECT 4 UNION SELECT 6) d
JOIN (SELECT '13:00' AS slot UNION SELECT '14:00' UNION SELECT '15:00' UNION SELECT '16:00') s
WHERE u.username = 'associate.dentist';
