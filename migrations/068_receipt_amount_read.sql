-- 068: the peso amount the patient's upload check read off the GCash / bank
-- receipt. The receptionist confirms (or corrects) it at Approve, and that
-- confirmed figure becomes payments.reservation_fee_paid, so a patient who
-- sent more than the fee gets the whole amount deducted at the cashier.
ALTER TABLE payments
  ADD COLUMN receipt_amount_read DECIMAL(10,2) NULL AFTER receipt_warnings;
