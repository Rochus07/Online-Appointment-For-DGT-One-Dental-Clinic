-- 025_clinic_slot_config.sql
-- Clinic operating hours are 10:00 AM to 5:00 PM with a 12:00 PM lunch break, so
-- the bookable slots are 10AM, 11AM, 1PM, 2PM, 3PM, 4PM, 5PM. Each slot holds a
-- flat 5 bookings (see slotCapacity() in api/appointments.php) - the roaming
-- dentist is called in and reassigned some of those 5 when a slot fills up.
--
-- Data only (settings rows) - no schema change.

INSERT INTO settings (setting_key, setting_value) VALUES
    ('time_slots', '10:00,11:00,13:00,14:00,15:00,16:00,17:00')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

UPDATE settings SET setting_value = '10:00' WHERE setting_key = 'clinic_open';
UPDATE settings SET setting_value = '17:00' WHERE setting_key = 'clinic_close';
