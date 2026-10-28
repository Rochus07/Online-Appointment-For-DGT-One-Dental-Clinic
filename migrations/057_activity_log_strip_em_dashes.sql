-- ---------------------------------------------------------------------------
--  057 — Strip leftover em dashes from existing activity_log rows.
--
--  logActivity() callers never write an em dash ("—") in an action description
--  any more (see CLAUDE.md / the no-em-dashes-anywhere memory) — each spot that
--  used to build one now joins with a comma or a colon instead. This migration
--  is a one-time cleanup for rows written before that fix, so the History Log
--  never shows an em dash even on old data.
--
--  Three distinct phrasings existed, each replaced with what the current code
--  actually emits for that same message:
--    1. "Sign-in blocked — account deactivated"              -> ": "
--    2. "...on Invoice #N — fully paid / partially paid..."   -> ": "
--    3. everything else (booking/walk-in/resubmit messages)   -> ", "
--
--  Idempotent — once a row's action no longer contains " — ", none of the
--  three UPDATEs match it again, so re-running this file is harmless.
--
--  Apply:  d:\XAMPP\mysql\bin\mysql.exe -u root dgtone_db < migrations\057_activity_log_strip_em_dashes.sql
-- ---------------------------------------------------------------------------
USE dgtone_db;

UPDATE activity_log
SET action = REPLACE(action, ' — ', ': ')
WHERE category = 'auth'
  AND action LIKE 'Sign-in blocked —%';

UPDATE activity_log
SET action = REPLACE(action, ' — ', ': ')
WHERE category = 'billing'
  AND (action LIKE '%— fully paid%' OR action LIKE '%— partially paid%');

UPDATE activity_log
SET action = REPLACE(action, ' — ', ', ')
WHERE action LIKE '%—%';
