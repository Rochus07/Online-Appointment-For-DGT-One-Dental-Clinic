-- ============================================================
--  Migration 052: OTP fallback for the email-existence check
-- ============================================================
-- When none of the 3 verification providers (migration 051 /
-- includes/email_verify.php) can confirm an address either way, the admin
-- Add User modal and the receptionist Walk-in "New patient" modal now
-- challenge the person for a 6-digit code sent to that address instead of
-- silently letting it through. A pending challenge lives here, one row per
-- email (replaced on every send/resend); a correct answer is recorded back
-- into email_verification_cache (source='otp_verified') so no other code
-- path needs to know an OTP was ever involved.
CREATE TABLE IF NOT EXISTS email_otp_challenges (
    email      VARCHAR(190) NOT NULL,
    otp_hash   CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    attempts   TINYINT      NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
