-- 033: Shorten the X-Ray service name for tidier display (homepage footer list,
-- booking pickers, admin Services). No functional change.
UPDATE services
SET service_name = 'Dental X-Ray'
WHERE service_name = 'X-Ray (Panoramic, Periapical)';
