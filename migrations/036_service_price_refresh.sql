-- 036: Refresh service base prices to realistic 2026 Philippine (provincial /
-- Bulacan) starting rates, researched from public dental price guides
-- (clinicfinderph.com, ngipenhub.com, desidental.ph). base_price is the
-- "starting from" figure for each treatment area. Adjust to the clinic's real
-- rate card when confirmed.
UPDATE services SET base_price =  1000.00 WHERE service_name = 'Restorative Dentistry';   -- fillings ₱800–5,000/tooth
UPDATE services SET base_price =  6000.00 WHERE service_name = 'Prosthodontics';           -- dentures/crowns ₱5,000–80,000/arch
UPDATE services SET base_price = 35000.00 WHERE service_name = 'Orthodontics';              -- braces ₱30,000–300,000
UPDATE services SET base_price = 45000.00 WHERE service_name = 'Dental Implants';           -- implant ₱40,000–150,000/tooth
UPDATE services SET base_price =   600.00 WHERE service_name = 'Pedodontics';               -- kids check-up / cleaning
UPDATE services SET base_price =   500.00 WHERE service_name = 'General Dentistry';         -- oral prophylaxis / consult ₱300–1,500
UPDATE services SET base_price =  6000.00 WHERE service_name = 'Cosmetic Dentistry';        -- veneers ₱3,000–35,000, whitening ₱3,000–25,000
UPDATE services SET base_price =  3000.00 WHERE service_name = 'Dental Surgery';            -- surgical extraction ₱1,500–15,000
UPDATE services SET base_price =  8000.00 WHERE service_name = 'Endodontics';               -- root canal ₱6,000–30,000/tooth
UPDATE services SET base_price =   500.00 WHERE service_name = 'Dental X-Ray';              -- periapical / panoramic ₱300–1,500
