-- ---------------------------------------------------------------------------
--  020 - attach a photo to each of the 10 treatment-area services
--
--  The image files live in uploads/services/ (copied from the ones the clinic
--  supplied). services.image stores the bare filename; every consumer builds
--  the URL as /dgt_dental_clinic/uploads/services/<image>. Matched to each row
--  by service_name so it is safe to re-run and independent of service_id.
--
--  Apply: d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\020_service_images.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

START TRANSACTION;

UPDATE services SET image = 'restorative_dentistry.jpg'    WHERE service_name = 'Restorative Dentistry';
UPDATE services SET image = 'prosthodontics.jpg'           WHERE service_name = 'Prosthodontics';
UPDATE services SET image = 'orthodontics.jpg'             WHERE service_name = 'Orthodontics';
UPDATE services SET image = 'dental_implants.jpg'          WHERE service_name = 'Dental Implants';
UPDATE services SET image = 'pedodontics.jpg'              WHERE service_name = 'Pedodontics';
UPDATE services SET image = 'general_dentistry.jpg'        WHERE service_name = 'General Dentistry';
UPDATE services SET image = 'cosmetic_dentistry.jpg'       WHERE service_name = 'Cosmetic Dentistry';
UPDATE services SET image = 'dental_surgery.jpg'           WHERE service_name = 'Dental Surgery';
UPDATE services SET image = 'endodontics.jpg'              WHERE service_name = 'Endodontics';
UPDATE services SET image = 'xray_panoramic_periapical.png' WHERE service_name = 'X-Ray (Panoramic, Periapical)';

COMMIT;
