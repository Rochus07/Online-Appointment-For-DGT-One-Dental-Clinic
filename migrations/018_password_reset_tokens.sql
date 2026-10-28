-- 018_password_reset_tokens.sql
-- "Forgot your password?" support: a single-use, time-limited reset token per user.
-- reset_token_hash stores SHA-256(raw token); the raw token only ever lives in the
-- e-mailed reset link. reset_token_expires_at is set to 15 minutes from creation.

ALTER TABLE users
    ADD COLUMN reset_token_hash       CHAR(64) DEFAULT NULL,
    ADD COLUMN reset_token_expires_at DATETIME DEFAULT NULL;
