-- ============================================================
--  Payment Decline & Resubmission workflow (online appointments)
--  Lets a receptionist reject just the reservation receipt on an
--  online booking (blurry image, reference mismatch, invalid
--  screenshot, ...) without cancelling the appointment itself.
--  The patient sees the reason, resubmits a corrected reference
--  number + receipt, and the appointment goes back to 'pending'
--  so it reappears in the receptionist's approval queue.
-- ============================================================

ALTER TABLE appointments
    MODIFY COLUMN status ENUM('pending','approved','completed','cancelled','rescheduled','payment_declined') NOT NULL DEFAULT 'pending',
    ADD COLUMN payment_decline_code VARCHAR(40) DEFAULT NULL AFTER decline_reason;
