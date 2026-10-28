-- ---------------------------------------------------------------------------
--  032 — PRC License No. for dentists.
--
--  Adds users.prc_license (VARCHAR 50, nullable). Only meaningful for the
--  dentist role — captured on the admin Users page's Add Doctor modal and
--  returned in the users API list/get responses.
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\032_dentist_prc_license.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

ALTER TABLE users ADD COLUMN prc_license VARCHAR(50) DEFAULT NULL AFTER degree;
