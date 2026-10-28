-- 009_split_cancellation_reason.sql
-- Split the single cancellation_reason into who submitted it:
--   patient_cancellation_reason  – the patient cancelled their own booking online
--   staff_cancellation_reason    – a receptionist / admin cancelled or declined it
-- The legacy cancellation_reason and decline_reason columns are kept and still
-- written for backward compatibility (the patient "submitted" poll screen reads
-- decline_reason).

ALTER TABLE appointments
    ADD COLUMN patient_cancellation_reason VARCHAR(255) DEFAULT NULL AFTER cancellation_reason,
    ADD COLUMN staff_cancellation_reason   VARCHAR(255) DEFAULT NULL AFTER patient_cancellation_reason;

-- Backfill existing rows. A decline is always staff-initiated. A plain
-- cancellation_reason with no decline can't be attributed reliably, so treat it
-- as patient-submitted (the common case for this app's self-service cancel).
UPDATE appointments
   SET staff_cancellation_reason = decline_reason
 WHERE decline_reason IS NOT NULL;

UPDATE appointments
   SET patient_cancellation_reason = cancellation_reason
 WHERE cancellation_reason IS NOT NULL
   AND decline_reason IS NULL;
