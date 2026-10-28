-- User Accounts rework: role-specific Add-account forms.
-- Dentists get specialties (comma-separated, same keys as services), a degree
-- and years of experience; receptionists/cashiers get years of experience; all
-- staff get an optional "about" blurb.
-- Safe to run once against an existing dental_clinic_system database.
USE dental_clinic_system;

ALTER TABLE users
    ADD COLUMN specialties      VARCHAR(255) DEFAULT NULL AFTER role,
    ADD COLUMN degree           VARCHAR(60)  DEFAULT NULL AFTER specialties,
    ADD COLUMN years_experience INT          DEFAULT NULL AFTER degree,
    ADD COLUMN about            TEXT         DEFAULT NULL AFTER years_experience;

UPDATE users SET specialties = 'general', degree = 'DMD', years_experience = 8 WHERE role = 'dentist' AND username = 'dentist1';
UPDATE users SET specialties = 'general', degree = 'DDS', years_experience = 4 WHERE role = 'dentist' AND username = 'dentist2';
