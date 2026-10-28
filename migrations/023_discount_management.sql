-- ------------------------------------------------------------
--  023_discount_management.sql
--  Admin-managed service promotions / discounts, applied by the
--  cashier on the Service Invoice and recorded on the payment.
-- ------------------------------------------------------------

-- Promotions the admin creates in Settings.
CREATE TABLE discounts (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    discount_name       VARCHAR(120) NOT NULL,
    discount_type       ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    discount_value      DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- percent (0-100) when 'percentage', peso amount when 'fixed'
    target_scope        ENUM('all','specific') NOT NULL DEFAULT 'all',
    applied_service_ids VARCHAR(1000) DEFAULT NULL,            -- JSON array of services.service_id; NULL when scope = 'all'
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- The promo applied to a bill, recorded alongside the statutory SC/PWD discount.
ALTER TABLE payments
    ADD COLUMN promo_discount_id     INT DEFAULT NULL AFTER discount,
    ADD COLUMN promo_discount_label  VARCHAR(160) DEFAULT NULL AFTER promo_discount_id,
    ADD COLUMN promo_discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER promo_discount_label,
    ADD CONSTRAINT fk_pay_promo FOREIGN KEY (promo_discount_id) REFERENCES discounts(id) ON DELETE SET NULL;

-- Remember which catalogue service an invoice line came from, so a
-- service-specific promo can be matched back to its line items.
ALTER TABLE invoice_items
    ADD COLUMN service_id INT DEFAULT NULL AFTER kind;

-- Sample promotions (one live, one draft).
INSERT INTO discounts (discount_name, discount_type, discount_value, target_scope, applied_service_ids, is_active) VALUES
('Back to School Promo', 'fixed', 350.00, 'all', NULL, 1),
('Preventive Care Promo (25% Off Cleaning and X-Ray)', 'percentage', 25.00, 'specific', '[5, 6, 10]', 0);
