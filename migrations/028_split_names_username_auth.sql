-- ============================================================
--  Migration 028 — split name fields, first.last usernames,
--  forced first-login password change
-- ============================================================
--  Advisor feedback: one identity model for every account.
--   * name is stored as First / Middle (optional) / Last
--   * username is auto-generated "first.last" and is the ONLY login credential
--   * admin-created staff get a system-generated temp password; must_change_password
--     flags that they are still on it (a soft reminder on the profile page, not a
--     hard gate) and is cleared when they set their own password.
--  full_name stays as an auto-maintained mirror ("First Middle Last") so the
--  ~28 display sites keep working untouched.
-- ------------------------------------------------------------

ALTER TABLE users
    ADD COLUMN first_name  VARCHAR(60) NOT NULL DEFAULT '' AFTER full_name,
    ADD COLUMN middle_name VARCHAR(60) DEFAULT NULL         AFTER first_name,
    ADD COLUMN last_name   VARCHAR(60) NOT NULL DEFAULT '' AFTER middle_name,
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password;

-- Backfill the parts from the existing single full_name
-- (first token -> first_name, the remainder -> last_name).
UPDATE users SET
    first_name = TRIM(SUBSTRING_INDEX(full_name, ' ', 1)),
    last_name  = TRIM(CASE WHEN LOCATE(' ', full_name) > 0
                           THEN SUBSTRING(full_name, LOCATE(' ', full_name) + 1)
                           ELSE full_name END)
WHERE first_name = '';

-- Standardize the seed / demo accounts to first.last with matching split names.
UPDATE users SET username='admin.demo',       first_name='Admin',     last_name='Demo',      full_name='Admin Demo'        WHERE username='admin';
UPDATE users SET username='elaine.silva',      first_name='Elaine',    last_name='Silva',     full_name='Elaine Silva'      WHERE username='dentist1';
UPDATE users SET username='associate.dentist', first_name='Associate', last_name='Dentist',   full_name='Associate Dentist' WHERE username='dentist2';
UPDATE users SET username='cashier.demo',      first_name='Cashier',   last_name='Demo',      full_name='Cashier Demo'      WHERE username='cashier1';
UPDATE users SET username='reception.demo',    first_name='Reception', last_name='Demo',      full_name='Reception Demo'    WHERE username='reception1';
UPDATE users SET username='dental.assistant',  first_name='Dental',    last_name='Assistant', full_name='Dental Assistant'  WHERE username='assistant1';
UPDATE users SET username='patient.demo',      first_name='Patient',   last_name='Demo',      full_name='Patient Demo'      WHERE username='patient1';
