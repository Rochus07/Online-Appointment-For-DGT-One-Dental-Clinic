-- 041_email_verification_otp.sql
-- Switch patient e-mail verification from a clickable link to a 6-digit OTP
-- code. verify_token_hash (SHA-256 of a 32-byte link token, no expiry) is
-- renamed to verify_otp_hash (SHA-256 of the 6-digit code) and gets a
-- matching expiry column, mirroring the reset_token_hash / reset_token_expires_at
-- pair already used for password reset.

ALTER TABLE users
    CHANGE COLUMN verify_token_hash verify_otp_hash CHAR(64) DEFAULT NULL,
    ADD COLUMN verify_otp_expires_at DATETIME DEFAULT NULL AFTER verify_otp_hash;
