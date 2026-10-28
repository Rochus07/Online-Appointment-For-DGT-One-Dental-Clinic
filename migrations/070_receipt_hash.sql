-- 070: SHA-256 of the reservation receipt image, so the same screenshot
-- can't be reused to pay for a second online booking. Older rows are
-- filled in from their files the first time a booking checks
-- (receiptImageInUse() in api/appointments.php).
ALTER TABLE payments
  ADD COLUMN receipt_hash CHAR(64) NULL AFTER receipt_amount_read,
  ADD INDEX idx_pay_receipt_hash (receipt_hash);
