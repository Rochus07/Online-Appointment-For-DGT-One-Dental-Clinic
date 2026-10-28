-- 013_counter_transactions.sql
-- Over-the-counter transactions recorded by the cashier from the Transaction
-- page ("+ Add Transaction") — product sales and manual adjustments that are not
-- tied to a patient appointment / billing queue entry.

CREATE TABLE counter_transactions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    description    VARCHAR(255) NOT NULL,
    customer_name  VARCHAR(120) DEFAULT NULL,
    amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash','gcash','bank') NOT NULL DEFAULT 'cash',
    reference_no   VARCHAR(40) DEFAULT NULL,
    cashier_id     INT DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ct_cashier FOREIGN KEY (cashier_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
