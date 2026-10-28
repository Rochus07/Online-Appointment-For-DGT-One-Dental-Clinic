-- 024_email_verification.sql
-- Patient e-mail verification. A new patient registers with is_verified = 0 and a
-- single-use token; verify_token_hash stores SHA-256(raw token) (the raw token
-- only ever appears in the e-mailed link, mirroring reset_token_hash). login.php
-- blocks a patient whose account is still is_verified = 0.
--
-- Every account that already exists predates verification, so grandfather them
-- all to verified.

ALTER TABLE users
    ADD COLUMN is_verified       TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN verify_token_hash CHAR(64)   DEFAULT NULL        AFTER is_verified;

UPDATE users SET is_verified = 1;
