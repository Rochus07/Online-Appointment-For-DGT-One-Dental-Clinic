-- 088: Grace period (Delayed / Standby), end-of-day No-Show batch, walk-in priority.
-- delayed_at: set by the system when an approved visit is not checked in 15 minutes
--   after its start time. The seat is released to walk-ins (capacity skips a row with
--   delayed_at set and no check-in). Checked in afterwards = "Arrived (Late)".
-- seat_released_online: 1 when an approved online booking was cancelled; its seat goes
--   to the walk-in schedule only and still counts as taken on the online calendar.
ALTER TABLE appointments
  ADD COLUMN delayed_at DATETIME NULL DEFAULT NULL AFTER checked_in_at,
  ADD COLUMN seat_released_online TINYINT(1) NOT NULL DEFAULT 0 AFTER delayed_at;
