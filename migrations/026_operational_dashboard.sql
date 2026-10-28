-- ============================================================
--  026  Operational dashboard support
--  - adds the 'dental_assistant' staff role
--  - adds users.last_seen_at for a lightweight presence signal
--    (touched once per request by the auth middleware)
--  - seeds one demo dental assistant account (password123)
-- ============================================================

ALTER TABLE users
    MODIFY COLUMN role
    ENUM('admin','dentist','cashier','receptionist','patient','dental_assistant')
    NOT NULL;

ALTER TABLE users
    ADD COLUMN last_seen_at DATETIME DEFAULT NULL AFTER is_verified;

CREATE INDEX idx_users_last_seen ON users(last_seen_at);

-- Demo chairside assistant. Plain password: password123 (same hash as the other seeds).
INSERT IGNORE INTO users (full_name, username, password, role, is_verified, contact_number, email)
VALUES ('Assistant One', 'assistant1',
        '$2y$10$nIofhnooOuY7SWfPpfzxeei/4HlUso4SeHwtJxUlSqFezAcX2q4mm',
        'dental_assistant', 1, '09170000007', 'assistant@drelainesilva.com');
