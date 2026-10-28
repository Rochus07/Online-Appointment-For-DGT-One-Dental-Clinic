-- ---------------------------------------------------------------------------
--  022 - richer payment details on the cashier "+ Add Transaction" flow
--
--  The over-the-counter transaction form now matches the Billing Service
--  Invoice payment block: split payment, per-method amounts, GCash number,
--  received amount / change, and a payment date.
--
--  reference_no keeps its meaning (GCash ref, or the bank ref for a single
--  bank payment); bank_reference is new and only used for a split that has a
--  bank portion alongside a GCash portion.
--
--  Apply: d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\022_counter_transaction_payment_details.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

ALTER TABLE counter_transactions
    MODIFY payment_method ENUM('cash','gcash','bank','split') NOT NULL DEFAULT 'cash',
    ADD COLUMN gcash_number    VARCHAR(20)   DEFAULT NULL      AFTER reference_no,
    ADD COLUMN bank_reference  VARCHAR(20)   DEFAULT NULL      AFTER bank_account,
    ADD COLUMN amount_cash     DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER bank_reference,
    ADD COLUMN amount_gcash    DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount_cash,
    ADD COLUMN amount_bank     DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount_gcash,
    ADD COLUMN received_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount_bank,
    ADD COLUMN change_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER received_amount,
    ADD COLUMN payment_date    DATE          DEFAULT NULL      AFTER change_amount;

-- Backfill so existing rows read sensibly.
UPDATE counter_transactions SET
    payment_date    = DATE(created_at),
    received_amount = amount,
    amount_cash     = CASE WHEN payment_method = 'cash'  THEN amount ELSE 0 END,
    amount_gcash    = CASE WHEN payment_method = 'gcash' THEN amount ELSE 0 END,
    amount_bank     = CASE WHEN payment_method = 'bank'  THEN amount ELSE 0 END;

-- A single bank payment now keeps its reference in bank_reference (reference_no
-- is the GCash ref going forward). Move existing bank rows across.
UPDATE counter_transactions
   SET bank_reference = reference_no, reference_no = NULL
 WHERE payment_method = 'bank' AND reference_no IS NOT NULL;
