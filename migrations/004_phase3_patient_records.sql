-- Phase 3: the Specialist's Patient Record + Dental Chart module.
-- One record per patient account: personal details (DOB, gender), dental &
-- medical history fields, and a tooth chart stored as JSON
-- ({ "11": "cured", "24": "missing", ... }).
-- Safe to run once against an existing dental_clinic_system database.
USE dental_clinic_system;

CREATE TABLE IF NOT EXISTS patient_records (
    record_id             INT AUTO_INCREMENT PRIMARY KEY,
    patient_id            INT NOT NULL UNIQUE,
    date_of_birth         DATE DEFAULT NULL,
    gender                ENUM('male','female','other') DEFAULT NULL,
    occlusion             VARCHAR(120) DEFAULT NULL,
    periodontal_condition VARCHAR(120) DEFAULT NULL,
    oral_hygiene          VARCHAR(120) DEFAULT NULL,
    denture_upper         VARCHAR(60)  DEFAULT NULL,
    denture_lower         VARCHAR(60)  DEFAULT NULL,
    abnormalities         VARCHAR(255) DEFAULT NULL,
    general_condition     TEXT DEFAULT NULL,
    medical_history       TEXT DEFAULT NULL,
    tooth_chart           LONGTEXT DEFAULT NULL,
    updated_by            INT DEFAULT NULL,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prec_patient FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_prec_by      FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
