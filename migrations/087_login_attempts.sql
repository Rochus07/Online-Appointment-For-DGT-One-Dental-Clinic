-- 087: brute-force protection (2026-10-23). One row per failed sign-in or
-- wrong 6-digit code; throttleLocked() counts the last 15 minutes.
CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_id  INT AUTO_INCREMENT PRIMARY KEY,
  scope       VARCHAR(20)  NOT NULL,          -- login / verify_otp / reset_otp
  ident       VARCHAR(190) NOT NULL,          -- lowercase username or e-mail
  ip          VARCHAR(45)  NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_la_ident (scope, ident, created_at),
  INDEX idx_la_ip (scope, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
