-- Phase 2: the Service Invoice / billing screen.
-- Adds SC/PWD discount, doctor's fee, withholding tax, the chosen form of
-- payment (cash / gcash / bank transfer) with its reference fields, received
-- amount and change; and a 'no_show' appointment status.
-- Safe to run once against an existing dental_clinic_system database.
USE dental_clinic_system;

ALTER TABLE appointments
    MODIFY COLUMN status
        ENUM('pending','scheduled','completed','cancelled','rescheduled','no_show')
        NOT NULL DEFAULT 'pending';

ALTER TABLE payments
    ADD COLUMN sc_pwd_discount  TINYINT(1)    NOT NULL DEFAULT 0     AFTER discount,
    ADD COLUMN sc_pwd_id        VARCHAR(40)   DEFAULT NULL           AFTER sc_pwd_discount,
    ADD COLUMN doctor_fee       DECIMAL(10,2) NOT NULL DEFAULT 0.00  AFTER sc_pwd_id,
    ADD COLUMN withholding_tax  DECIMAL(10,2) NOT NULL DEFAULT 0.00  AFTER doctor_fee,
    ADD COLUMN payment_mode     ENUM('cash','gcash','bank') DEFAULT NULL AFTER withholding_tax,
    ADD COLUMN amount_bank      DECIMAL(10,2) NOT NULL DEFAULT 0.00  AFTER amount_gcash,
    ADD COLUMN bank_branch      VARCHAR(120)  DEFAULT NULL           AFTER gcash_reference_number,
    ADD COLUMN bank_account     VARCHAR(60)   DEFAULT NULL           AFTER bank_branch,
    ADD COLUMN bank_reference   VARCHAR(60)   DEFAULT NULL           AFTER bank_account,
    ADD COLUMN payment_date     DATE          DEFAULT NULL           AFTER bank_reference,
    ADD COLUMN received_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00  AFTER payment_date,
    ADD COLUMN change_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00  AFTER received_amount;
