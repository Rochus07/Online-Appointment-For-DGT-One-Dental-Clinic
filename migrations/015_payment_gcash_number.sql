-- 015_payment_gcash_number.sql
-- The cashier Billing screen's GCash payment block captures the payer's GCash
-- mobile number in addition to the 13-digit reference number.

ALTER TABLE payments
    ADD COLUMN gcash_number VARCHAR(20) DEFAULT NULL AFTER gcash_reference_number;
