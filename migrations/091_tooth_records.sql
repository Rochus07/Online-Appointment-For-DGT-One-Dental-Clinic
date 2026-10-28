-- 091: per-tooth condition, quick tags and notes per visit (2026-10-26, thesis defense).
-- One row per tooth per visit (appointment, or the date when charted outside a visit).
-- patient_records.tooth_chart stays the current status map; the latest row per tooth
-- holds its current tags / note, older rows are the history shown at later visits.

CREATE TABLE IF NOT EXISTS tooth_records (
  record_id      INT AUTO_INCREMENT PRIMARY KEY,
  patient_id     INT NOT NULL,
  tooth          TINYINT UNSIGNED NOT NULL,
  status         VARCHAR(12) DEFAULT NULL,      -- NULL = healthy / normal
  tags           VARCHAR(500) DEFAULT NULL,     -- quick tags, '|' separated
  note           VARCHAR(500) DEFAULT NULL,
  appointment_id INT DEFAULT NULL,
  visit_date     DATE NOT NULL,
  recorded_by    INT DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tr_patient (patient_id, tooth, visit_date),
  KEY idx_tr_appt (appointment_id),
  CONSTRAINT fk_tr_patient FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_appt FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
  CONSTRAINT fk_tr_by FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
