-- ---------------------------------------------------------------------------
--  019 - replace the service catalogue with the clinic's 10 treatment areas
--
--  The old procedure-level catalogue (Oral Prophylaxis, Light Cure Filling,
--  Dentures, Crown - PFM, Root Canal, Braces, Veneers, Full Crown, Tooth
--  Extraction, Teeth Whitening, Dental Implant, + the inactive "Dental Checkup
--  & Cleaning") is replaced by 10 treatment-area services:
--
--    1 Restorative Dentistry   2 Prosthodontics        3 Orthodontics
--    4 Dental Implants          5 Pedodontics           6 General Dentistry
--    7 Cosmetic Dentistry       8 Dental Surgery        9 Endodontics
--   10 X-Ray (Panoramic, Periapical)
--
--  Assumes the default seed (service_id 1..12 as shipped in schema.sql). The
--  two demo procedures / appointments that pointed at the removed rows are
--  repointed first so no FK is violated; existing bookings against ids 1..10
--  keep a valid (renamed) service.
--
--  Apply: d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\019_replace_service_catalogue.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

START TRANSACTION;

-- Repoint the demo data off services 6 (Braces) and 12 (Checkup) before they
-- are renamed / removed.  Appt 5 was a braces fitting -> Orthodontics (id 3);
-- appt 6 was a checkup & cleaning -> General Dentistry (id 6).
UPDATE appointment_services SET service_id = 3 WHERE service_id = 6;
UPDATE appointments         SET service_id = 3 WHERE service_id = 6;
UPDATE appointment_services SET service_id = 6 WHERE service_id = 12;
UPDATE appointments         SET service_id = 6 WHERE service_id = 12;

-- Drop the two now-unreferenced rows (11 Dental Implant, 12 Dental Checkup).
DELETE FROM services WHERE service_id IN (11, 12);

-- Rewrite ids 1..10 into the new treatment-area catalogue.
UPDATE services SET service_name='Restorative Dentistry', specialties='general', duration_minutes=60,
  description='Repairs and rebuilds teeth damaged by decay, wear or trauma using fillings, inlays, onlays, crowns and bridges to restore normal function and appearance.',
  features='Tooth-coloured fillings\nInlays, onlays and crowns\nBridges for missing teeth',
  base_price=1500.00, is_active=1 WHERE service_id=1;

UPDATE services SET service_name='Prosthodontics', specialties='prosthodontist', duration_minutes=60,
  description='Replaces missing teeth and restores the bite with removable and fixed prostheses - complete and partial dentures, bridges and implant-supported restorations.',
  features='Complete and partial dentures\nFixed and implant-supported bridges\nFull-mouth rehabilitation',
  base_price=6500.00, is_active=1 WHERE service_id=2;

UPDATE services SET service_name='Orthodontics', specialties='orthodontist', duration_minutes=45,
  description='Corrects crooked teeth and misaligned bites with braces and clear aligners, gradually guiding the teeth and jaws into their ideal positions.',
  features='Metal and ceramic braces\nClear aligner therapy\nRegular adjustments and monitoring',
  base_price=45000.00, is_active=1 WHERE service_id=3;

UPDATE services SET service_name='Dental Implants', specialties='oral_surgeon,prosthodontist', duration_minutes=90,
  description='Permanently replaces missing teeth with a titanium post placed in the jawbone and topped with a custom crown, preserving bone and restoring full chewing strength.',
  features='Single and multiple implants\nImplant-supported bridges and dentures\nBone preservation',
  base_price=55000.00, is_active=1 WHERE service_id=4;

UPDATE services SET service_name='Pedodontics', specialties='pediatric', duration_minutes=30,
  description='Gentle, child-focused dental care - first visits, cleanings, fluoride, sealants and fillings - that builds healthy habits and a positive attitude toward the dentist.',
  features='Child-friendly cleanings and exams\nFluoride treatment and sealants\nHabit and growth monitoring',
  base_price=800.00, is_active=1 WHERE service_id=5;

UPDATE services SET service_name='General Dentistry', specialties='general', duration_minutes=30,
  description='Routine preventive and diagnostic care - check-ups, professional cleaning, fillings and oral-health advice - to keep your teeth and gums healthy year round.',
  features='Comprehensive oral examination\nProfessional cleaning and polishing\nPreventive advice and fillings',
  base_price=800.00, is_active=1 WHERE service_id=6;

UPDATE services SET service_name='Cosmetic Dentistry', specialties='general,prosthodontist', duration_minutes=60,
  description='Improves the look of your smile through teeth whitening, veneers, cosmetic bonding and tooth-coloured restorations for a brighter, more even appearance.',
  features='In-clinic teeth whitening\nPorcelain veneers and bonding\nSmile-makeover planning',
  base_price=7000.00, is_active=1 WHERE service_id=7;

UPDATE services SET service_name='Dental Surgery', specialties='oral_surgeon', duration_minutes=45,
  description='Surgical treatment of the teeth, gums and jaw - simple and surgical extractions, impacted wisdom teeth and minor oral surgery - performed under local anaesthesia.',
  features='Simple and surgical extractions\nImpacted wisdom-tooth removal\nMinor oral surgery',
  base_price=2500.00, is_active=1 WHERE service_id=8;

UPDATE services SET service_name='Endodontics', specialties='endodontist', duration_minutes=90,
  description='Saves badly infected or damaged teeth with root canal treatment - removing the inflamed pulp, disinfecting the canals and sealing them to stop further infection.',
  features='Root canal treatment\nRetreatment of failed root canals\nManagement of dental pain and abscess',
  base_price=9500.00, is_active=1 WHERE service_id=9;

UPDATE services SET service_name='X-Ray (Panoramic, Periapical)', specialties='general', duration_minutes=15,
  description='Diagnostic dental imaging - panoramic X-rays for a full view of the jaws and periapical X-rays for detail on individual teeth and roots - to support accurate diagnosis and treatment planning.',
  features='Panoramic (full-mouth) X-ray\nPeriapical (single-tooth) X-ray\nDigital, low-dose imaging',
  base_price=800.00, is_active=1 WHERE service_id=10;

COMMIT;
