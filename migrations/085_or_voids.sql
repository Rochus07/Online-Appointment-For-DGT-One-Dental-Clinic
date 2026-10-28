-- 085: Voided Official Receipts, admin only (2026-10-21).
-- or_issued gains the per-method split of each receipt so a void can take
-- exactly that payment back off the bill, plus voided_at.
-- or_voids: one row per voided receipt number, issued (tied to a payment) or a
-- blank spoiled sheet that was never issued. A voided number can never be issued.
ALTER TABLE or_issued
  ADD COLUMN pay_cash  DECIMAL(10,2) DEFAULT NULL AFTER amount,
  ADD COLUMN pay_gcash DECIMAL(10,2) DEFAULT NULL AFTER pay_cash,
  ADD COLUMN pay_bank  DECIMAL(10,2) DEFAULT NULL AFTER pay_gcash,
  ADD COLUMN voided_at DATETIME DEFAULT NULL AFTER issued_at;

CREATE TABLE IF NOT EXISTS or_voids (
  void_id        INT AUTO_INCREMENT PRIMARY KEY,
  or_no          VARCHAR(20) NOT NULL,
  series_id      INT NULL,
  or_id          INT NULL,
  payment_id     INT NULL,
  appointment_id INT NULL,
  patient_name   VARCHAR(150) NULL,
  amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  day_seq        INT NULL,
  issued_at      DATETIME NULL,
  cashier_id     INT NULL,
  cashier_name   VARCHAR(150) NULL,
  voided_by      INT NULL,
  voided_by_name VARCHAR(150) NULL,
  reason_code    VARCHAR(30) NOT NULL,
  reason         VARCHAR(255) NOT NULL,
  voided_at      DATETIME NOT NULL,
  UNIQUE KEY uq_void_or_no (or_no),
  KEY idx_void_at (voided_at),
  CONSTRAINT fk_void_series  FOREIGN KEY (series_id)  REFERENCES or_series(series_id) ON DELETE SET NULL,
  CONSTRAINT fk_void_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE SET NULL,
  CONSTRAINT fk_void_cashier FOREIGN KEY (cashier_id) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_void_admin   FOREIGN KEY (voided_by)  REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
