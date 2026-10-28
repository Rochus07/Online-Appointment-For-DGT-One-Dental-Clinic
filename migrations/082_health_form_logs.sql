-- Patient Information Record history (2026-10-20). Every submission is kept
-- as its own timestamped entry instead of overwriting the previous one. After
-- submitting, the form is locked until the next review is due (6 months).
-- The latest answers stay in patient_records.health_form for prefill; the
-- summary no longer goes into medical_history (that box is the dentist's notes).
CREATE TABLE IF NOT EXISTS health_form_logs (
  log_id       INT AUTO_INCREMENT PRIMARY KEY,
  patient_id   INT NOT NULL,
  answers      TEXT NOT NULL,
  summary      TEXT NOT NULL,
  submitted_at DATETIME NOT NULL,
  submitted_by INT DEFAULT NULL,
  KEY idx_hfl_patient (patient_id, submitted_at),
  CONSTRAINT fk_hfl_patient FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing answers become the first log entry.
INSERT INTO health_form_logs (patient_id, answers, summary, submitted_at, submitted_by)
SELECT r.patient_id,
       JSON_EXTRACT(r.health_form, '$.answers'),
       JSON_UNQUOTE(JSON_EXTRACT(r.health_form, '$.summary')),
       r.health_form_at, r.patient_id
FROM patient_records r
WHERE r.health_form IS NOT NULL AND r.health_form_at IS NOT NULL
  AND JSON_EXTRACT(r.health_form, '$.answers') IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM health_form_logs l WHERE l.patient_id = r.patient_id);

-- Take the copied summary back out of Medical History, keeping the dentist's own text.
UPDATE patient_records
SET medical_history = NULLIF(TRIM(BOTH '\n' FROM REPLACE(medical_history, JSON_UNQUOTE(JSON_EXTRACT(health_form, '$.summary')), '')), '')
WHERE health_form IS NOT NULL AND JSON_EXTRACT(health_form, '$.summary') IS NOT NULL
  AND medical_history LIKE CONCAT('%', JSON_UNQUOTE(JSON_EXTRACT(health_form, '$.summary')), '%');
