-- Reprice the 10 services closer to real-world Philippine dental-clinic rates
-- (per the user's request, based on published 2026 PH price guides). Also
-- moves every price away from the ₱500 reservation fee so no service price
-- coincidentally equals it again.
UPDATE services SET base_price = 1500.00  WHERE service_name = 'Restorative Dentistry';
UPDATE services SET base_price = 15000.00 WHERE service_name = 'Prosthodontics';
UPDATE services SET base_price = 45000.00 WHERE service_name = 'Orthodontics';
UPDATE services SET base_price = 60000.00 WHERE service_name = 'Dental Implants';
UPDATE services SET base_price = 1200.00  WHERE service_name = 'Pedodontics';
UPDATE services SET base_price = 800.00   WHERE service_name = 'General Dentistry';
UPDATE services SET base_price = 15000.00 WHERE service_name = 'Cosmetic Dentistry';
UPDATE services SET base_price = 5000.00  WHERE service_name = 'Dental Surgery';
UPDATE services SET base_price = 12000.00 WHERE service_name = 'Endodontics';
UPDATE services SET base_price = 800.00   WHERE service_name = 'Dental X-Ray';
