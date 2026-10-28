-- ---------------------------------------------------------------------------
--  021 - allow a split payment mode on the cashier Service Invoice
--
--  The cashier can now settle one bill with more than one method at once
--  (e.g. part cash + part GCash). The per-method running totals still live in
--  payments.amount_cash / amount_gcash / amount_bank; payment_mode just records
--  that the settle was a split so the receipt renders the breakdown.
--
--  Apply: d:\XAMPP\mysql\bin\mysql.exe -u root dental_clinic_system < migrations\021_payment_mode_split.sql
-- ---------------------------------------------------------------------------
USE dental_clinic_system;

ALTER TABLE payments
    MODIFY payment_mode ENUM('cash','gcash','bank','split') DEFAULT NULL;
