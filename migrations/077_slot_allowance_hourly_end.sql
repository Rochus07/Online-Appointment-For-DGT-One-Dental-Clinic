-- 077: corrects 076 (2026-10-18). Each visit ends on the hour and the next
-- starts 15 minutes after it: 10:00-11:00, 11:15-12:00, (lunch),
-- 1:15-2:00, 2:15-3:00, 3:15-4:00, 4:15-5:00.
UPDATE settings SET setting_value = '10:00,11:15,13:15,14:15,15:15,16:15' WHERE setting_key = 'time_slots';

-- Dentist grids: 13:00 -> 13:15, 15:30 -> 15:15, and give back the 4 PM hour
-- (as 16:15) wherever the 3 PM hour is worked, as it was before 076.
UPDATE IGNORE dentist_schedules SET time_slot = '13:15' WHERE time_slot = '13:00';
UPDATE IGNORE dentist_schedules SET time_slot = '15:15' WHERE time_slot = '15:30';
INSERT IGNORE INTO dentist_schedules (dentist_id, day_of_week, time_slot, is_active)
  SELECT dentist_id, day_of_week, '16:15', is_active FROM dentist_schedules WHERE time_slot = '15:15';
DELETE FROM dentist_schedules WHERE time_slot NOT IN ('10:00','11:15','13:15','14:15','15:15','16:15');

UPDATE appointments SET time_slot = '13:15'
 WHERE time_slot = '13:00' AND scheduled_date >= CURDATE() AND status IN ('pending','approved','rescheduled','payment_declined');
UPDATE appointments SET time_slot = '15:15'
 WHERE time_slot = '15:30' AND scheduled_date >= CURDATE() AND status IN ('pending','approved','rescheduled','payment_declined');
