-- 084: Official Receipt (OR) / invoice series, managed by the cashier (2026-10-21).
-- or_series: a block of pre-printed receipt numbers (e.g. 000301 to 000320).
--   next_no = the receipt that must be loaded in the printer for the next payment.
--   status: active (in use), used_up (every number issued), closed (reset by the cashier).
-- or_issued: one row per receipt number given to a payment (day_seq = transaction # that day).
CREATE TABLE IF NOT EXISTS or_series (
  series_id   INT AUTO_INCREMENT PRIMARY KEY,
  start_no    INT UNSIGNED NOT NULL,
  end_no      INT UNSIGNED NOT NULL,
  next_no     INT UNSIGNED NOT NULL,
  digits      TINYINT UNSIGNED NOT NULL DEFAULT 6,
  status      ENUM('active','used_up','closed') NOT NULL DEFAULT 'active',
  created_by  INT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at   DATETIME NULL,
  CONSTRAINT fk_or_series_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS or_issued (
  or_id          INT AUTO_INCREMENT PRIMARY KEY,
  or_no          VARCHAR(20) NOT NULL,
  series_id      INT NOT NULL,
  payment_id     INT NULL,
  appointment_id INT NULL,
  amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  day_seq        INT NOT NULL DEFAULT 1,
  issued_by      INT NULL,
  issued_at      DATETIME NOT NULL,
  UNIQUE KEY uq_or_no (or_no),
  KEY idx_or_issued_day (issued_at),
  CONSTRAINT fk_or_issued_series FOREIGN KEY (series_id) REFERENCES or_series(series_id) ON DELETE CASCADE,
  CONSTRAINT fk_or_issued_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE SET NULL,
  CONSTRAINT fk_or_issued_user FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments ADD COLUMN or_number VARCHAR(20) DEFAULT NULL AFTER payment_status;
