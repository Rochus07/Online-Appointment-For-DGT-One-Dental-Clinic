-- 014_invoice_items.sql
-- Editable Service Invoice line items for the cashier Billing screen
-- (modules/cashier/billing.php). The dentist's logged procedures seed the
-- invoice; the cashier may add extra Service / Add-on rows before marking the
-- bill Paid. Stored per payment so a saved invoice can be reopened.

CREATE TABLE invoice_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    payment_id  INT NOT NULL,
    kind        ENUM('service','addon') NOT NULL DEFAULT 'service',
    description VARCHAR(255) NOT NULL,
    qty         INT NOT NULL DEFAULT 1,
    unit        VARCHAR(40) DEFAULT NULL,
    unit_cost   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_ii_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE
) ENGINE=InnoDB;
