-- 090: excess reservation returned as change (2026-10-26).
-- When the reservation fee paid online is more than the final bill, the cashier
-- hands the difference back at settlement. It is recorded here (and included in
-- change_amount) and subtracted from revenue on the settlement day.
ALTER TABLE payments
  ADD COLUMN reservation_refund DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER reservation_fee_paid;
