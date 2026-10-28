-- Services page rework: a flat catalogue (no Basic/Advanced/Specialized grouping)
-- with an image, duration, feature list and specialty tags (which kinds of
-- dentist perform the service, stored comma-separated:
-- general, endodontist, orthodontist, periodontist, prosthodontist,
-- oral_surgeon, pediatric).
-- The old `category` column is kept (now nullable) but no longer used by the UI.
-- Safe to run once against an existing dental_clinic_system database.
USE dental_clinic_system;

ALTER TABLE services
    MODIFY COLUMN category ENUM('Basic','Advanced','Specialized') DEFAULT NULL,
    ADD COLUMN specialties      VARCHAR(255) DEFAULT NULL AFTER category,
    ADD COLUMN duration_minutes INT NOT NULL DEFAULT 30   AFTER specialties,
    ADD COLUMN features         TEXT DEFAULT NULL          AFTER description,
    ADD COLUMN image            VARCHAR(255) DEFAULT NULL  AFTER features;

UPDATE services SET specialties = 'general' WHERE specialties IS NULL;
