-- 083: the reservation fee counts as revenue on the day it is paid (2026-10-21).
-- reservation_paid_at  = when the patient paid it (online booking / resubmit, or the walk-in counter)
-- reservation_confirmed_at = when the front desk accepted it (online Approve; walk-ins at once)
-- Revenue reports count the fee on reservation_paid_at once it is confirmed, and
-- the rest of the bill on the settlement day as before.
ALTER TABLE payments
  ADD COLUMN reservation_paid_at DATETIME DEFAULT NULL AFTER reservation_fee_paid,
  ADD COLUMN reservation_confirmed_at DATETIME DEFAULT NULL AFTER reservation_paid_at;

-- Existing fees: paid when the booking was made; confirmed if the booking got past approval.
UPDATE payments p
JOIN appointments a ON a.appointment_id = p.appointment_id
SET p.reservation_paid_at = p.created_at,
    p.reservation_confirmed_at = CASE
      WHEN a.booking_type = 'walk_in'
        OR a.status IN ('approved', 'rescheduled', 'completed', 'no_show')
        OR a.checked_in_at IS NOT NULL
      THEN p.created_at END
WHERE p.reservation_fee_paid > 0;
