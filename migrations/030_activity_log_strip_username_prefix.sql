-- ---------------------------------------------------------------------------
--  030 — Tidy existing activity_log rows: drop the leading "<username> " that
--  older logActivity() calls prepended to the action text.
--
--  The History Log's User column already shows who performed the action, so the
--  description now starts with the verb ("Checked in Rosa Lim", "Signed in").
--  This strips the redundant prefix from rows written before that change and
--  capitalises the new first letter.
--
--  Idempotent — a row whose action no longer starts with "<username> " is
--  skipped, so re-running it is harmless.
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\030_activity_log_strip_username_prefix.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

UPDATE activity_log
SET action = CONCAT(
        UPPER(LEFT(SUBSTRING(action, CHAR_LENGTH(username) + 2), 1)),
        SUBSTRING(action, CHAR_LENGTH(username) + 3)
    )
WHERE username <> ''
  AND action LIKE CONCAT(username, ' %');
