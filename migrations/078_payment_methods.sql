-- 078: admin-defined extra online payment methods (2026-10-18).
-- Besides GCash and Bank Transfer, the admin can add more (e.g. Maya, BDO)
-- from Settings. A booking paid with one stores reservation_method = 'm<id>'
-- plus a snapshot of its name in reservation_method_label.
CREATE TABLE IF NOT EXISTS payment_methods (
    method_id      INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(60)  NOT NULL,
    account_name   VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(60)  DEFAULT NULL,
    qr_image       VARCHAR(255) DEFAULT NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payments
    MODIFY reservation_method VARCHAR(20) DEFAULT NULL,
    ADD COLUMN reservation_method_label VARCHAR(80) DEFAULT NULL AFTER reservation_method;
