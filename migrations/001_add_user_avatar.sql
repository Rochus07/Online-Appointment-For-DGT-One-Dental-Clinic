-- Adds the profile-photo column used by the "My Profile" page.
-- Safe to run once against an existing dental_clinic_system database.
USE dental_clinic_system;

ALTER TABLE users
    ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER email;
