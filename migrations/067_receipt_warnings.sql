-- 067: keep the patient-side receipt upload check results (blurry, wrong
-- orientation, not a GCash receipt, amount below the fee, date not today,
-- AI-generated / edited) so the receptionist sees them on the receipt viewer.
-- Filled by api/appointments.php action=create / resubmit_payment.
ALTER TABLE payments
  ADD COLUMN receipt_warnings TEXT NULL AFTER receipt_flag_reason;
