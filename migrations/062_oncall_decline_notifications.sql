-- 062: on-call decline workflow + structured notifications (2026-10)
--
-- notifications gain an optional link to the appointment they are about and a
-- machine-readable type, so the bell / notification pages can render a card
-- with live appointment details and action buttons instead of a flat string.
--   type: 'oncall_assigned' (roaming dentist), 'oncall_declined' (receptionist),
--         'appointment_rescheduled' (patient); NULL for older free-text rows.
--
-- appointments gain the on-call decline record. A declined assignment keeps its
-- status (approved / rescheduled) but loses its dentist (dentist_id = NULL)
-- until the receptionist reassigns or reschedules it.

ALTER TABLE notifications
  ADD COLUMN appointment_id INT DEFAULT NULL AFTER user_id,
  ADD COLUMN type VARCHAR(40) DEFAULT NULL AFTER appointment_id,
  ADD CONSTRAINT fk_notif_appt FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL;
CREATE INDEX idx_notif_user_type ON notifications(user_id, type);

ALTER TABLE appointments
  ADD COLUMN oncall_declined_by INT DEFAULT NULL,
  ADD COLUMN oncall_declined_at DATETIME DEFAULT NULL,
  ADD COLUMN oncall_decline_reason VARCHAR(255) DEFAULT NULL,
  ADD CONSTRAINT fk_appt_oncall_declined_by FOREIGN KEY (oncall_declined_by) REFERENCES users(user_id) ON DELETE SET NULL;
