-- 076: 15-minute allowance between visits (2026-10-18).
-- Visits stay 1 hour, with a 15-minute gap before the next one:
-- 10:00-11:00, 11:15-12:15, (lunch), 1:00-2:00, 2:15-3:15, 3:30-4:30.

UPDATE settings SET setting_value = '10:00,11:15,13:00,14:15,15:30' WHERE setting_key = 'time_slots';

-- Dentist weekly grids: move each old hour to its new start; drop the rest.
UPDATE IGNORE dentist_schedules SET time_slot = '11:15' WHERE time_slot = '11:00';
UPDATE IGNORE dentist_schedules SET time_slot = '14:15' WHERE time_slot = '14:00';
UPDATE IGNORE dentist_schedules SET time_slot = '15:30' WHERE time_slot = '15:00';
DELETE FROM dentist_schedules WHERE time_slot NOT IN ('10:00','11:15','13:00','14:15','15:30');

-- Upcoming bookings at a time that no longer exists move to the matching new slot.
UPDATE appointments SET time_slot = '11:15'
 WHERE time_slot = '11:00' AND scheduled_date >= CURDATE() AND status IN ('pending','approved','rescheduled','payment_declined');
UPDATE appointments SET time_slot = '14:15'
 WHERE time_slot = '14:00' AND scheduled_date >= CURDATE() AND status IN ('pending','approved','rescheduled','payment_declined');
UPDATE appointments SET time_slot = '15:30'
 WHERE time_slot IN ('15:00','16:00','17:00') AND scheduled_date >= CURDATE() AND status IN ('pending','approved','rescheduled','payment_declined');
