-- 065: admin-editable Terms & Conditions and Privacy Policy.
-- `settings.setting_value` is VARCHAR(255), far too short for a legal text,
-- so each document gets its own row here. No seed rows: until an admin saves
-- one, includes/legal.php falls back to the built-in default text.

CREATE TABLE IF NOT EXISTS legal_documents (
    doc_key     VARCHAR(20) PRIMARY KEY,          -- 'terms' | 'privacy'
    body        MEDIUMTEXT  NOT NULL,             -- light markup, see renderLegal()
    updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by  INT         NULL,
    CONSTRAINT fk_legal_updated_by FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
