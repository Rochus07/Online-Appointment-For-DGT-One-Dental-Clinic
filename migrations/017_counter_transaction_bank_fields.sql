-- 017_counter_transaction_bank_fields.sql
-- The cashier "+ Add Transaction" modal now captures the sender's bank / branch
-- and account number for a Bank Transfer payment (same fields as the Billing
-- screen). reference_no still holds the bank reference number.

ALTER TABLE counter_transactions
    ADD COLUMN bank_branch  VARCHAR(120) DEFAULT NULL AFTER reference_no,
    ADD COLUMN bank_account VARCHAR(60)  DEFAULT NULL AFTER bank_branch;
