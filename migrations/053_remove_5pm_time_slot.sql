-- 053: Drop the 5:00 PM (17:00) clinic time slot. The clinic closes at 17:00
-- (clinic_close, unchanged) and a slot's own visit is a full 1-hour chair
-- booking, so a 5:00 PM slot would run until 6:00 PM — past closing. The last
-- bookable slot is now 4:00 PM (16:00), which runs exactly to closing time.
UPDATE settings
SET setting_value = '10:00,11:00,13:00,14:00,15:00,16:00'
WHERE setting_key = 'time_slots';
