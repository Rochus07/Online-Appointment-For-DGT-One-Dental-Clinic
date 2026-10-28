-- 080: dentist-managed availability (2026-10-19).
-- When an in-house dentist turns hours off on My Availability, each live
-- booking inside those hours is either moved to an on-duty roaming dentist
-- ('reassigned') or flagged for the patient to pick a new time
-- ('needs_reschedule'). The receptionist works these from the
-- "Action Needed: Dentist Schedule Change" queue. Cleared on reschedule,
-- reassign or dismiss.

ALTER TABLE appointments
  ADD COLUMN sched_change_status ENUM('reassigned','needs_reschedule') DEFAULT NULL,
  ADD COLUMN sched_change_at DATETIME DEFAULT NULL,
  ADD COLUMN sched_change_from INT DEFAULT NULL,
  ADD INDEX idx_appt_sched_change (sched_change_status),
  ADD CONSTRAINT fk_appt_sched_change_from FOREIGN KEY (sched_change_from) REFERENCES users(user_id) ON DELETE SET NULL;
