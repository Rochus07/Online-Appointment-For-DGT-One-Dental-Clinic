-- Expand the dental-assistant roster (migration 038) with a fuller staff
-- profile: separate First / Middle / Last name (mirroring the users table
-- convention), a phone number + e-mail, years of experience and an About
-- bio. Assistants still have NO account — no username, no password, no login.
ALTER TABLE dental_assistants
  ADD COLUMN first_name       VARCHAR(60)  NOT NULL DEFAULT '' AFTER assistant_id,
  ADD COLUMN middle_name      VARCHAR(60)  DEFAULT NULL        AFTER first_name,
  ADD COLUMN last_name        VARCHAR(60)  NOT NULL DEFAULT '' AFTER middle_name,
  ADD COLUMN contact_number   VARCHAR(30)  DEFAULT NULL        AFTER full_name,
  ADD COLUMN email            VARCHAR(120) DEFAULT NULL        AFTER contact_number,
  ADD COLUMN years_experience INT          DEFAULT NULL        AFTER email,
  ADD COLUMN about            TEXT         DEFAULT NULL        AFTER years_experience;

-- Backfill first/last name for any existing roster row from its full_name.
UPDATE dental_assistants
   SET first_name = SUBSTRING_INDEX(full_name, ' ', 1),
       last_name  = TRIM(SUBSTRING(full_name, LOCATE(' ', full_name) + 1))
 WHERE LOCATE(' ', full_name) > 0 AND first_name = '';
UPDATE dental_assistants SET first_name = full_name WHERE first_name = '';
