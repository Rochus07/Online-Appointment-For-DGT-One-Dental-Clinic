-- ============================================================
--  Dr. Elaine Silva Dental Clinic - Management System
--  Database Schema  (MySQL 8 / MariaDB 10.4+, PHP 8.2 PDO)
-- ============================================================

DROP DATABASE IF EXISTS dental_clinic_system;
CREATE DATABASE dental_clinic_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE dental_clinic_system;

-- ------------------------------------------------------------
--  users
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120)  NOT NULL,                   -- auto-maintained mirror of "first middle last" (migration 028)
    first_name      VARCHAR(60)   NOT NULL DEFAULT '',        -- migration 028
    middle_name     VARCHAR(60)   DEFAULT NULL,               -- migration 028, optional
    last_name       VARCHAR(60)   NOT NULL DEFAULT '',        -- migration 028
    username        VARCHAR(60)   NOT NULL UNIQUE,            -- auto-generated "first.last"; the only login credential
    password        VARCHAR(255)  NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,       -- migration 028: 1 = still on an admin-issued temp password (soft reminder on the profile page, not a gate); cleared when the user sets their own
    role            ENUM('admin','dentist','cashier','receptionist','patient','dental_assistant') NOT NULL,
    guardian_user_id INT DEFAULT NULL,       -- parent / guardian account managing a minor (migration 089)
    adult_account_at DATETIME DEFAULT NULL,  -- when the dependent, at 18, got their own login (migration 089)
    doctor_type     ENUM('owner','roaming') NOT NULL DEFAULT 'roaming',   -- dentists: in-house Owner vs on-call Roaming/Associate
    specialties     VARCHAR(255)  DEFAULT NULL,   -- dentists: comma-separated (see services.specialties)
    degree          VARCHAR(60)   DEFAULT NULL,   -- dentists
    prc_license     VARCHAR(50)   DEFAULT NULL,   -- dentists: PRC License No. (migration 032)
    years_experience INT          DEFAULT NULL,   -- staff
    about           TEXT          DEFAULT NULL,   -- staff bio
    contact_number  VARCHAR(30)   DEFAULT NULL,
    date_of_birth   DATE          DEFAULT NULL,   -- staff only (migration 072); patients use patient_records
    email           VARCHAR(120)  DEFAULT NULL,
    avatar          VARCHAR(255)  DEFAULT NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    is_verified     TINYINT(1)    NOT NULL DEFAULT 0,   -- patient e-mail verification; staff seeds are set to 1 below
    last_seen_at    DATETIME      DEFAULT NULL,   -- lightweight presence signal, touched once per request by auth_middleware
    verify_otp_hash        CHAR(64) DEFAULT NULL,   -- SHA-256 of the 6-digit e-mail-verification OTP (migration 041)
    verify_otp_expires_at  DATETIME DEFAULT NULL,   -- 10 min from creation
    reset_token_hash       CHAR(64) DEFAULT NULL,   -- SHA-256 of the raw password-reset token
    reset_token_expires_at DATETIME DEFAULT NULL,   -- 15 min from creation
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
ALTER TABLE users ADD CONSTRAINT fk_users_guardian FOREIGN KEY (guardian_user_id) REFERENCES users(user_id) ON DELETE SET NULL;

CREATE INDEX idx_users_last_seen ON users(last_seen_at);

-- ------------------------------------------------------------
--  services
-- ------------------------------------------------------------
CREATE TABLE services (
    service_id       INT AUTO_INCREMENT PRIMARY KEY,
    service_name     VARCHAR(120) NOT NULL,
    category         ENUM('Basic','Advanced','Specialized') DEFAULT NULL,   -- legacy, unused
    specialties      VARCHAR(255) DEFAULT NULL,   -- comma-separated: general,endodontist,orthodontist,periodontist,prosthodontist,oral_surgeon,pediatric
    duration_minutes INT NOT NULL DEFAULT 30,
    description      TEXT,
    short_description VARCHAR(180) DEFAULT NULL,  -- independent homepage-card blurb (migration 045)
    features         TEXT,                        -- one feature per line
    image            VARCHAR(255) DEFAULT NULL,
    base_price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity_unit    VARCHAR(40) DEFAULT NULL,  -- NULL = single-session (qty 1); else charged per 'tooth' / 'unit' / 'arch' / ... (migration 061)
    allows_installment TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = orthodontic: the cashier may take an Installment (migration 069)
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  dental_assistants  (migration 038 — NON-ACCOUNT chairside staff: no login)
--  Physical duties: room prep, instrument sterilization, four-handed assisting
--  (instrument passing, suction), material prep. Tracked only by the front-desk
--  daily duty check-in (assistant_shifts) and assist tags on visits
--  (appointments.checkin_assistant_id / treatment_assistant_id).
-- ------------------------------------------------------------
CREATE TABLE dental_assistants (
    assistant_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT           DEFAULT NULL,          -- migration 058, optional login (users.role = 'dental_assistant')
    first_name       VARCHAR(60)   NOT NULL DEFAULT '',   -- migration 039
    middle_name      VARCHAR(60)   DEFAULT NULL,          -- migration 039, optional
    last_name        VARCHAR(60)   NOT NULL DEFAULT '',   -- migration 039
    full_name        VARCHAR(120) NOT NULL,               -- auto-maintained mirror, personName()
    contact_number   VARCHAR(30)   DEFAULT NULL,          -- migration 039
    email            VARCHAR(120)  DEFAULT NULL,          -- migration 039; required once a login exists (migration 058)
    years_experience INT           DEFAULT NULL,          -- migration 039
    about            TEXT          DEFAULT NULL,          -- migration 039
    avatar       VARCHAR(255) DEFAULT NULL,          -- reserved; UI renders initials for now
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dental_assistants_user (user_id),
    CONSTRAINT fk_dental_assistants_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE assistant_shifts (                       -- one row = "on duty that day"
    id           INT AUTO_INCREMENT PRIMARY KEY,
    assistant_id INT NOT NULL,
    shift_date   DATE NOT NULL,
    marked_by    INT DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_assistant_day (assistant_id, shift_date),
    KEY idx_shift_date (shift_date),
    CONSTRAINT fk_shift_assistant FOREIGN KEY (assistant_id) REFERENCES dental_assistants(assistant_id) ON DELETE CASCADE,
    CONSTRAINT fk_shift_by        FOREIGN KEY (marked_by)    REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  appointments
-- ------------------------------------------------------------
CREATE TABLE appointments (
    appointment_id  INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    treated_patient_id INT DEFAULT NULL,   -- set when booking_for='other': the real users.user_id of the person actually treated (a separate account from patient_id, the booking account) — 2026-09, migration 043
    dentist_id      INT DEFAULT NULL,
    service_id      INT DEFAULT NULL,
    treatment_plan_id INT DEFAULT NULL,   -- booked from a dentist's follow-up recommendation (migration 066); FK added after treatment_plans below
    booking_for     ENUM('self','other') NOT NULL DEFAULT 'self',
    patient_name    VARCHAR(120) NOT NULL,
    patient_age     INT DEFAULT NULL,
    patient_phone   VARCHAR(20)  DEFAULT NULL,
    relationship    VARCHAR(60)  DEFAULT NULL,
    insurance_provider VARCHAR(120) DEFAULT NULL,
    scheduled_date  DATE NOT NULL,
    time_slot       VARCHAR(20)  NOT NULL,
    booking_type    ENUM('online','walk_in') NOT NULL DEFAULT 'online',
    status          ENUM('pending','approved','completed','cancelled','rescheduled','payment_declined','no_show') NOT NULL DEFAULT 'pending',   -- 'no_show' (migration 064): approved visit the patient never checked in for; 'payment_declined' (migration 056): the receptionist rejected only the online reservation receipt — the appointment itself stays intact, the patient resubmits, and it returns to 'pending'
    checked_in_at   DATETIME DEFAULT NULL,   -- front-desk check-in (migration 027); "Ongoing" = set + status not completed/cancelled
    delayed_at      DATETIME DEFAULT NULL,   -- not checked in 15 min after start: Delayed / Standby, seat released (migration 088)
    seat_released_online TINYINT(1) NOT NULL DEFAULT 0,   -- cancelled approved online booking: seat stays with walk-ins (migration 088)
    ready_for_treatment_at DATETIME DEFAULT NULL,   -- assistant-verified prep complete (migration 060)
    ready_notes     VARCHAR(255) DEFAULT NULL,   -- optional free-text prep notes (migration 060)
    reminder_sent_at DATETIME DEFAULT NULL,   -- day-before reminder notification sent guard (migration 048)
    missed_notified_at DATETIME DEFAULT NULL,   -- "you missed your appointment" notification sent guard (migration 049)
    checkin_assistant_id   INT DEFAULT NULL,   -- assisting staff tagged at check-in (migration 038)
    treatment_assistant_id INT DEFAULT NULL,   -- assisting staff tagged at treatment logging (migration 038)
    notes           TEXT,
    decline_reason  VARCHAR(255) DEFAULT NULL,
    payment_decline_code VARCHAR(40) DEFAULT NULL,   -- structured reason for a payment_declined status (migration 056): blurry_image / reference_mismatch / invalid_screenshot / other
    cancellation_reason     VARCHAR(255) DEFAULT NULL,   -- legacy mirror, still written
    patient_cancellation_reason VARCHAR(255) DEFAULT NULL,   -- patient cancelled online
    staff_cancellation_reason   VARCHAR(255) DEFAULT NULL,   -- receptionist/admin cancelled or declined
    reschedule_reason       VARCHAR(255) DEFAULT NULL,
    previous_scheduled_date DATE         DEFAULT NULL,
    oncall_declined_by      INT          DEFAULT NULL,   -- roaming dentist who declined this on-call assignment (migration 062); dentist_id is cleared to NULL until the front desk reassigns/reschedules
    oncall_declined_at      DATETIME     DEFAULT NULL,
    oncall_decline_reason   VARCHAR(255) DEFAULT NULL,
    sched_change_status     ENUM('reassigned','needs_reschedule') DEFAULT NULL,   -- dentist turned these hours off (migration 080): moved to a roaming dentist, or the patient must pick a new time
    sched_change_at         DATETIME     DEFAULT NULL,
    sched_change_from       INT          DEFAULT NULL,   -- the in-house dentist whose schedule change caused it
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appt_patient   FOREIGN KEY (patient_id)   REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_treated_patient FOREIGN KEY (treated_patient_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_service FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_checkin_asst FOREIGN KEY (checkin_assistant_id)   REFERENCES dental_assistants(assistant_id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_treat_asst   FOREIGN KEY (treatment_assistant_id) REFERENCES dental_assistants(assistant_id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_oncall_declined_by FOREIGN KEY (oncall_declined_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_appt_sched_change_from FOREIGN KEY (sched_change_from) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_appt_date         ON appointments(scheduled_date);
CREATE INDEX idx_appt_status       ON appointments(status);
CREATE INDEX idx_appt_checked_in   ON appointments(checked_in_at);
CREATE INDEX idx_appt_ready        ON appointments(ready_for_treatment_at);
CREATE INDEX idx_appt_dentist_slot ON appointments(dentist_id, scheduled_date, time_slot);
CREATE INDEX idx_appt_treated_patient ON appointments(treated_patient_id);
CREATE INDEX idx_appt_sched_change  ON appointments(sched_change_status);

-- ------------------------------------------------------------
--  treatment_plans  (dentist-recommended follow-up services, migration 066)
-- ------------------------------------------------------------
CREATE TABLE treatment_plans (
    plan_id                  INT AUTO_INCREMENT PRIMARY KEY,
    patient_id               INT NOT NULL,            -- booking account (sees + books the plan)
    treated_patient_id       INT DEFAULT NULL,        -- the real person treated when booked "for someone else"
    consultation_id          INT NOT NULL,            -- appointments.appointment_id of the visit that recommended it
    service_id               INT NOT NULL,
    assigned_dentist_id      INT NOT NULL,            -- locked: the dentist who logged the visit
    timeframe_recommendation VARCHAR(20) NOT NULL,    -- '1 Week' | '2 Weeks' | '1 Month' | '3 Months' | '6 Months'
    due_date                 DATE NOT NULL,           -- visit date + timeframe
    status                   ENUM('Pending','Scheduled','Completed') NOT NULL DEFAULT 'Pending',
    created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tp_patient      FOREIGN KEY (patient_id)          REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_treated      FOREIGN KEY (treated_patient_id)  REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_tp_consultation FOREIGN KEY (consultation_id)     REFERENCES appointments(appointment_id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_service      FOREIGN KEY (service_id)          REFERENCES services(service_id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_dentist      FOREIGN KEY (assigned_dentist_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_tp_consultation (consultation_id),
    KEY idx_tp_patient_status (patient_id, status)
) ENGINE=InnoDB;

ALTER TABLE appointments
    ADD CONSTRAINT fk_appt_treatment_plan FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plans(plan_id) ON DELETE SET NULL;

-- ------------------------------------------------------------
--  appointment_services  (procedures availed per visit)
-- ------------------------------------------------------------
CREATE TABLE appointment_services (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id  INT NOT NULL,
    service_id      INT NOT NULL,
    qty             INT NOT NULL DEFAULT 1,              -- units logged (per-unit services only; migration 061)
    price           DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- LINE TOTAL = qty x unit price
    added_by        INT NULL,                            -- cashier who added it at checkout; NULL = dentist (migration 086)
    CONSTRAINT fk_as_appt    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE,
    CONSTRAINT fk_as_service FOREIGN KEY (service_id)     REFERENCES services(service_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  discounts  (admin-managed service promotions, applied by the cashier)
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
--  payments
-- ------------------------------------------------------------
CREATE TABLE payments (
    payment_id              INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id          INT NOT NULL,
    total_amount            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    promo_discount_id       INT DEFAULT NULL,                 -- admin promotion applied at billing
    promo_discount_label    VARCHAR(160) DEFAULT NULL,
    promo_discount_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sc_pwd_discount         TINYINT(1) NOT NULL DEFAULT 0,
    sc_pwd_id               VARCHAR(40) DEFAULT NULL,
    sc_pwd_type             ENUM('senior','pwd') DEFAULT NULL,  -- migration 075
    sc_tin                  VARCHAR(20) DEFAULT NULL,            -- migration 075
    doctor_fee              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    withholding_tax         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_mode            ENUM('cash','gcash','bank','split') DEFAULT NULL,
    reservation_fee_paid    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reservation_refund      DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- excess reservation handed back as change (090)
    reservation_paid_at     DATETIME DEFAULT NULL,   -- when the fee was paid (migration 083): revenue counts the fee on this day
    reservation_confirmed_at DATETIME DEFAULT NULL,  -- front desk accepted it (online Approve; walk-ins at once)
    reservation_method      VARCHAR(20) DEFAULT NULL,   -- cash / bank / gcash / paymaya, or 'm<id>' for an admin-added method (migration 078)
    reservation_method_label VARCHAR(80) DEFAULT NULL,  -- name snapshot for an admin-added method
    reservation_reference   VARCHAR(40) DEFAULT NULL,
    reservation_receipt     VARCHAR(255) DEFAULT NULL,
    receipt_flag_suspected  TINYINT(1) NOT NULL DEFAULT 0,
    receipt_flag_reason     VARCHAR(255) DEFAULT NULL,
    receipt_warnings        TEXT DEFAULT NULL,           -- patient-side upload check errors (migration 067)
    receipt_amount_read     DECIMAL(10,2) DEFAULT NULL,  -- amount the upload check read off the receipt (migration 068)
    receipt_hash            CHAR(64)      DEFAULT NULL,  -- SHA-256 of the receipt image; one screenshot per booking (migration 070)
    amount_cash             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_gcash            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_bank             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    gcash_reference_number  VARCHAR(13) DEFAULT NULL UNIQUE,
    gcash_number            VARCHAR(20) DEFAULT NULL,
    bank_branch             VARCHAR(120) DEFAULT NULL,
    bank_account            VARCHAR(60) DEFAULT NULL,
    bank_reference          VARCHAR(60) DEFAULT NULL,
    payment_date            DATE DEFAULT NULL,
    received_amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    change_amount           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    remaining_balance       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_status          ENUM('unpaid','partially_paid','fully_paid') NOT NULL DEFAULT 'unpaid',
    or_number               VARCHAR(20) DEFAULT NULL,   -- latest Official Receipt no. issued for this bill (migration 084)
    paid_at                 TIMESTAMP NULL DEFAULT NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_appt  FOREIGN KEY (appointment_id)    REFERENCES appointments(appointment_id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_promo FOREIGN KEY (promo_discount_id) REFERENCES discounts(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX idx_pay_receipt_hash ON payments(receipt_hash);

-- ------------------------------------------------------------
--  invoice_items  (editable Service Invoice line items, cashier Billing screen)
-- ------------------------------------------------------------
CREATE TABLE invoice_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    payment_id  INT NOT NULL,
    kind        ENUM('service','addon') NOT NULL DEFAULT 'service',
    service_id  INT DEFAULT NULL,                          -- catalogue service a line came from (for promo matching)
    description VARCHAR(255) NOT NULL,
    qty         INT NOT NULL DEFAULT 1,
    unit        VARCHAR(40) DEFAULT NULL,
    unit_cost   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_ii_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  notifications
-- ------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    appointment_id  INT DEFAULT NULL,   -- the appointment this is about (migration 062)
    type            VARCHAR(40) DEFAULT NULL,   -- oncall_assigned / oncall_declined / appointment_rescheduled; NULL = plain text (migration 062)
    title           VARCHAR(120) NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_appt FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX idx_notif_user_type ON notifications(user_id, type);

-- ------------------------------------------------------------
--  patient_records  (Specialist's dental chart + medical history)
-- ------------------------------------------------------------
CREATE TABLE patient_records (
    record_id             INT AUTO_INCREMENT PRIMARY KEY,
    patient_id            INT NOT NULL UNIQUE,
    date_of_birth         DATE DEFAULT NULL,
    dental_age            VARCHAR(40) DEFAULT NULL,   -- free-text "Child / Adult Age" clinical note
    gender                ENUM('male','female','other') DEFAULT NULL,
    address                  VARCHAR(255) DEFAULT NULL,   -- front-desk editable
    emergency_contact_name   VARCHAR(120) DEFAULT NULL,   -- front-desk editable
    emergency_contact_number VARCHAR(30)  DEFAULT NULL,   -- front-desk editable
    allergies               VARCHAR(500) DEFAULT NULL,   -- patient-entered on My Profile (migration 071); NULL = not answered
    occlusion             VARCHAR(120) DEFAULT NULL,
    periodontal_condition VARCHAR(120) DEFAULT NULL,
    oral_hygiene          VARCHAR(120) DEFAULT NULL,
    denture_upper         VARCHAR(60)  DEFAULT NULL,
    denture_lower         VARCHAR(60)  DEFAULT NULL,
    abnormalities         VARCHAR(255) DEFAULT NULL,
    general_condition     TEXT DEFAULT NULL,
    medical_history       TEXT DEFAULT NULL,
    health_form           TEXT DEFAULT NULL,      -- Patient Information Record answers (JSON), migration 081
    health_form_at        DATETIME DEFAULT NULL,  -- when the patient last submitted it
    tooth_chart           LONGTEXT DEFAULT NULL,          -- JSON: { "11": "cured", ... }
    updated_by            INT DEFAULT NULL,
    updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prec_patient FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_prec_by      FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Patient Information Record history: one row per submission (migration 082).
CREATE TABLE health_form_logs (
    log_id       INT AUTO_INCREMENT PRIMARY KEY,
    patient_id   INT NOT NULL,
    answers      TEXT NOT NULL,        -- JSON answers
    summary      TEXT NOT NULL,        -- readable summary shown to the dentist
    submitted_at DATETIME NOT NULL,
    submitted_by INT DEFAULT NULL,
    KEY idx_hfl_patient (patient_id, submitted_at),
    CONSTRAINT fk_hfl_patient FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Per-tooth condition, quick tags and notes per visit (migration 091).
CREATE TABLE tooth_records (
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
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  blocked_dates
-- ------------------------------------------------------------
-- Admin-added online payment methods besides GCash / Bank Transfer (migration 078).
CREATE TABLE payment_methods (
    method_id      INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(60)  NOT NULL,
    account_name   VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(60)  DEFAULT NULL,
    qr_image       VARCHAR(255) DEFAULT NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cashier's Official Receipt series (migration 084): a block of pre-printed
-- receipt numbers; next_no must match the receipt loaded in the printer.
CREATE TABLE or_series (
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

-- One row per receipt number given to a payment (day_seq = transaction # that day).
CREATE TABLE or_issued (
    or_id          INT AUTO_INCREMENT PRIMARY KEY,
    or_no          VARCHAR(20) NOT NULL,
    series_id      INT NOT NULL,
    payment_id     INT NULL,
    appointment_id INT NULL,
    amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pay_cash       DECIMAL(10,2) DEFAULT NULL,   -- per-method split of this receipt (migration 085)
    pay_gcash      DECIMAL(10,2) DEFAULT NULL,
    pay_bank       DECIMAL(10,2) DEFAULT NULL,
    day_seq        INT NOT NULL DEFAULT 1,
    issued_by      INT NULL,
    issued_at      DATETIME NOT NULL,
    voided_at      DATETIME DEFAULT NULL,        -- set when an admin voids it (migration 085)
    UNIQUE KEY uq_or_no (or_no),
    KEY idx_or_issued_day (issued_at),
    CONSTRAINT fk_or_issued_series FOREIGN KEY (series_id) REFERENCES or_series(series_id) ON DELETE CASCADE,
    CONSTRAINT fk_or_issued_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE SET NULL,
    CONSTRAINT fk_or_issued_user FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Voided receipt numbers, issued or blank spoiled sheets (migration 085, admin only).
CREATE TABLE or_voids (
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

-- Brute-force protection (migration 087): failed sign-ins / wrong codes.
CREATE TABLE login_attempts (
  attempt_id  INT AUTO_INCREMENT PRIMARY KEY,
  scope       VARCHAR(20)  NOT NULL,
  ident       VARCHAR(190) NOT NULL,
  ip          VARCHAR(45)  NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_la_ident (scope, ident, created_at),
  INDEX idx_la_ip (scope, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE blocked_dates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    blocked_date DATE NOT NULL UNIQUE,
    reason       VARCHAR(255) DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  dentist_schedules  (per-dentist weekly working grid — migration 031)
--  day_of_week 0=Sun … 6=Sat (PHP date('w')); time_slot is one of the clinic
--  time_slots. Online booking offers only the in-house (Owner) dentist's active
--  day+slot combos; the receptionist pickers only list on-duty dentists.
--  Duty mode (In-House Owner / Roaming Associate) lives on users.doctor_type.
--  Managed from the admin-only "Dentist Schedules" page.
-- ------------------------------------------------------------
CREATE TABLE dentist_schedules (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dentist_id  INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    time_slot   VARCHAR(5) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dentist_day_slot (dentist_id, day_of_week, time_slot),
    KEY idx_ds_lookup (day_of_week, time_slot, is_active),
    CONSTRAINT fk_ds_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  dentist_absences  (one-off sick day / emergency leave — migration 040)
--  A single-date override on top of the recurring weekly grid above: a row
--  here means the dentist is OFF duty that date regardless of what the grid
--  says. Checked inside dentistBookableSlots() so every consumer of
--  dentistWorksOn()/dentistOnDuty() picks it up automatically (the admin
--  dashboard's Doctors on Duty card, receptionist duty checks, online booking
--  availability for the owner dentist). Managed from the admin dashboard's
--  Doctors on Duty drill-down.
-- ------------------------------------------------------------
CREATE TABLE dentist_absences (
    absence_id   INT AUTO_INCREMENT PRIMARY KEY,
    dentist_id   INT NOT NULL,
    absence_date DATE NOT NULL,
    reason       VARCHAR(191) DEFAULT NULL,
    marked_by    INT DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dentist_absence_date (dentist_id, absence_date),
    KEY idx_absence_date (absence_date),
    CONSTRAINT fk_absence_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_absence_by      FOREIGN KEY (marked_by)  REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- dentist_day_schedules (migration 079): per-date working hours that replace
-- the weekly grid for one exact date. slots = CSV of HH:MM ('' = off that day).
CREATE TABLE dentist_day_schedules (
    dentist_id  INT          NOT NULL,
    work_date   DATE         NOT NULL,
    slots       VARCHAR(255) NOT NULL DEFAULT '',
    updated_by  INT          DEFAULT NULL,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (dentist_id, work_date),
    CONSTRAINT fk_day_sched_dentist FOREIGN KEY (dentist_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  settings  (key/value store)
-- ------------------------------------------------------------
CREATE TABLE settings (
    setting_key   VARCHAR(60) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  legal_documents  (admin-editable Terms & Conditions / Privacy Policy, migration 065)
-- ------------------------------------------------------------
CREATE TABLE legal_documents (
    doc_key     VARCHAR(20) PRIMARY KEY,          -- 'terms' | 'privacy'
    body        MEDIUMTEXT  NOT NULL,             -- light markup, see includes/legal.php renderLegal()
    updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by  INT         NULL,
    CONSTRAINT fk_legal_updated_by FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  counter_transactions  (cashier: unbooked walk-in dental services / manual billing adjustments)
-- ------------------------------------------------------------
CREATE TABLE counter_transactions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    description    VARCHAR(255) NOT NULL,
    customer_name  VARCHAR(120) DEFAULT NULL,
    amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash','gcash','bank','split') NOT NULL DEFAULT 'cash',
    reference_no   VARCHAR(40) DEFAULT NULL,     -- GCash ref, or the bank ref for a single bank payment
    gcash_number   VARCHAR(20) DEFAULT NULL,
    bank_branch    VARCHAR(120) DEFAULT NULL,
    bank_account   VARCHAR(60)  DEFAULT NULL,
    bank_reference VARCHAR(20)  DEFAULT NULL,    -- only for a split that has a bank portion alongside GCash
    amount_cash    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_gcash   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_bank    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    received_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    change_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_date    DATE DEFAULT NULL,
    cashier_id     INT DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ct_cashier FOREIGN KEY (cashier_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Staff activity / audit trail — powers the admin-only "Activity Log" page.
-- Written by logActivity() from the auth / appointment / records / billing
-- endpoints; username + role are snapshot at write time. (migration 029)
CREATE TABLE activity_log (
    log_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id    INT             DEFAULT NULL,
    username   VARCHAR(60)     NOT NULL DEFAULT '',
    role       VARCHAR(30)     NOT NULL DEFAULT '',
    category   VARCHAR(20)     NOT NULL,              -- auth | appointments | records | billing
    action     VARCHAR(255)    NOT NULL,
    ip         VARCHAR(45)     DEFAULT NULL,
    PRIMARY KEY (log_id),
    KEY idx_activity_created  (created_at),
    KEY idx_activity_category (category),
    KEY idx_activity_role     (role),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Caches a "does this mailbox actually exist?" lookup result (migration 050,
-- generalized from Gmail-only to any domain by migration 051) so the same
-- address is never re-checked against the provider chain within the cache
-- window — see includes/email_verify.php.
CREATE TABLE email_verification_cache (
    email       VARCHAR(190) NOT NULL,
    exists_flag TINYINT(1)   NOT NULL,   -- 1 = mailbox confirmed to exist, 0 = confirmed not to
    source      VARCHAR(20)  NOT NULL,   -- 'abstract' | 'emailverify' | 'mailboxlayer' | 'fail_open' | 'otp_verified'
    checked_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email)
) ENGINE=InnoDB;

-- A pending "confirm you own this address" OTP challenge (migration 052) —
-- the Add User / Walk-in email-existence check's fallback for an address
-- none of the 3 providers above could confirm either way. One row per email,
-- overwritten on every (re)send; a correct answer deletes the row and writes
-- exists_flag=1, source='otp_verified' into email_verification_cache above.
CREATE TABLE email_otp_challenges (
    email      VARCHAR(190) NOT NULL,
    otp_hash   CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    attempts   TINYINT      NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (email)
) ENGINE=InnoDB;

-- ============================================================
--  SEED DATA
-- ============================================================

-- Default accounts. Plain password for every account is:  password123
-- Hash below is a valid bcrypt hash of "password123".
INSERT INTO users (full_name, first_name, last_name, username, password, role, specialties, degree, years_experience, contact_number, email) VALUES
('Admin Demo',        'Admin',     'Demo',      'admin.demo',       '$2y$10$nIofhnooOuY7SWfPpfzxeei/4HlUso4SeHwtJxUlSqFezAcX2q4mm', 'admin',        NULL,        NULL,  NULL, '09170000001', 'admin@drelainesilva.com'),
('Elaine Silva',      'Elaine',    'Silva',     'elaine.silva',      '$2y$10$nIofhnooOuY7SWfPpfzxeei/4HlUso4SeHwtJxUlSqFezAcX2q4mm', 'dentist',      'general',   'Doctor of Dental Medicine', 8,    '09170000002', 'dentist@drelainesilva.com'),
('Associate Dentist', 'Associate', 'Dentist',   'associate.dentist', '$2y$10$nIofhnooOuY7SWfPpfzxeei/4HlUso4SeHwtJxUlSqFezAcX2q4mm', 'dentist',      'general',   'Doctor of Dental Surgery', 4,    '09170000006', 'dentist2@drelainesilva.com'),
('Cashier Demo',      'Cashier',   'Demo',      'cashier.demo',      '$2y$10$nIofhnooOuY7SWfPpfzxeei/4HlUso4SeHwtJxUlSqFezAcX2q4mm', 'cashier',      NULL,        NULL,  NULL, '09170000003', 'cashier@drelainesilva.com'),
('Reception Demo',    'Reception', 'Demo',      'reception.demo',    '$2y$10$nIofhnooOuY7SWfPpfzxeei/4HlUso4SeHwtJxUlSqFezAcX2q4mm', 'receptionist', NULL,        NULL,  NULL, '09170000004', 'reception@drelainesilva.com');

-- Dental assistants are non-account staff (migration 038) — roster only, no login.
INSERT INTO dental_assistants (first_name, last_name, full_name) VALUES ('Assistant', 'Dentist', 'Assistant Dentist');

-- Staff accounts only. There is no seeded patient — patients self-register
-- (register.php) or are created by staff from the Users / walk-in / records
-- screens. Seed accounts skip e-mail verification.
UPDATE users SET is_verified = 1;

-- Dr. Elaine Silva is the in-house Owner Dentist; the associate stays roaming (default).
UPDATE users SET doctor_type = 'owner' WHERE username = 'elaine.silva';

-- Seed every In-House (owner) dentist with the full clinic working grid
-- (Mon–Sat, every time slot) — matching seedDefaultOwnerSchedule() in
-- includes/functions.php, which applies the same default whenever a dentist
-- is created or switched to In-House from the admin Users page. A Roaming
-- dentist gets no default and starts with an empty grid; the admin fills one
-- in on the Dentist Schedules page.
INSERT INTO dentist_schedules (dentist_id, day_of_week, time_slot)
SELECT u.user_id, d.dow, s.slot
FROM users u
JOIN (SELECT 1 AS dow UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) d
JOIN (SELECT '10:00' AS slot UNION SELECT '11:00' UNION SELECT '13:00'
      UNION SELECT '14:00' UNION SELECT '15:00' UNION SELECT '16:00' UNION SELECT '17:00') s
WHERE u.role = 'dentist' AND u.is_active = 1 AND u.doctor_type = 'owner';

-- Service catalogue - the clinic's finalized 21-service price list (see migration 061).
INSERT INTO services (service_name, specialties, base_price, quantity_unit, image, short_description, description) VALUES
('Adjustment', 'orthodontist', 1000.00, NULL, 'Adjustment.jpg',
 'Routine tightening and check of your braces.',
 'A regular orthodontic visit where the dentist checks your progress and adjusts the wires and brackets so your teeth keep moving into place.'),
('Biteplane Impression / Installation', 'orthodontist', 5000.00, NULL, 'Biteplane Impression_Installition.jpg',
 'A custom biteplane to correct your bite and protect your teeth.',
 'Impressions are taken to make a removable biteplane appliance, which is then fitted to help open or correct the bite and guard teeth against grinding.'),
('Braces Removal', 'orthodontist', 3500.00, NULL, 'Braces Removal.jpg',
 'Safe removal of brackets and wires once treatment is done.',
 'Brackets, wires and leftover bonding material are carefully removed and the teeth are polished, ready for your retainer.'),
('Bridge', 'prosthodontist', 15000.00, 'unit', 'bridge.jpg',
 'A fixed bridge that fills the gap left by missing teeth.',
 'A fixed prosthesis anchored to the neighbouring teeth replaces one or more missing teeth, restoring your bite and smile. Charged per unit.'),
('Consult', 'general', 700.00, NULL, 'Consult.jpg',
 'A check-up and talk with the dentist about your concern.',
 'An examination and consultation where the dentist looks at your concern, explains your options and recommends a treatment plan.'),
('Dentures', 'prosthodontist', 12000.00, 'arch', 'dentures.jpg',
 'Removable replacement teeth made to fit your mouth.',
 'Custom removable dentures replace missing teeth on the upper or lower jaw, helping you chew and smile with confidence. Charged per arch.'),
('Extraction (Bunot)', 'general,oral_surgeon', 1500.00, 'tooth', 'Extraction (Bunot).jpg',
 'Gentle removal of a damaged or problem tooth.',
 'A tooth that is badly decayed, broken or causing problems is removed under local anaesthesia, with aftercare instructions for healing. Charged per tooth.'),
('Frenectomy', 'oral_surgeon,periodontist', 7000.00, NULL, 'Frenectomy.jpg',
 'Releases a tight lip or tongue tie.',
 'A minor procedure that trims or releases a tight frenum (the tissue under the lip or tongue) to improve movement, speech or gum health.'),
('Gingivectomy', 'periodontist', 8000.00, 'quadrant', 'Gingivectomy.jpg',
 'Reshapes overgrown or diseased gum tissue.',
 'Excess or unhealthy gum tissue is removed and reshaped to treat gum disease or improve the look of your smile. Charged per quadrant.'),
('New Ortho / Braces Installation', 'orthodontist', 45000.00, NULL, 'New Ortho  Braces Installation.jpg',
 'Start your braces treatment for a straighter smile.',
 'Brackets and wires are bonded to your teeth to begin orthodontic treatment that gradually straightens teeth and corrects the bite.'),
('Oral Prophylaxis (Cleaning)', 'general,periodontist', 1500.00, NULL, 'Oral Prophylaxis (Cleaning).jpg',
 'Professional cleaning to remove plaque and tartar.',
 'Plaque and tartar are scaled away and the teeth are polished, helping prevent cavities and gum disease.'),
('RB/Wire', 'orthodontist', 1200.00, NULL, 'RBWirewebp.jpg',
 'Replacement rubber bands or wire for your braces.',
 'Worn or broken rubber bands or an orthodontic wire are replaced so your braces keep working as planned.'),
('Resto (Pasta)', 'general', 1500.00, 'tooth surface', 'Resto (Pasta).jpg',
 'A tooth-coloured filling to repair a cavity.',
 'Decay is removed and the tooth is restored with a tooth-coloured filling that blends with your natural teeth. Charged per tooth surface.'),
('Retainer Impression / Installation', 'orthodontist', 6000.00, NULL, 'Retainer Impression Installation.jpg',
 'A custom retainer to keep your teeth in place.',
 'Impressions are taken to make a retainer that holds your teeth in their new position after orthodontic treatment, then it is fitted and adjusted.'),
('Root Canal Treatment (RCT)', 'endodontist', 8000.00, 'tooth', 'Root Canal Treatment (RCT).jpg',
 'Saves a badly infected or damaged tooth.',
 'The infected pulp inside the tooth is removed, the canals are cleaned and sealed, and the tooth is prepared for a final restoration. Charged per tooth.'),
('Surgery', 'oral_surgeon', 10000.00, NULL, 'Surgery.jpg',
 'Oral surgery such as impacted tooth removal.',
 'Surgical procedures such as removing impacted wisdom teeth, performed with local anaesthesia and full aftercare guidance.'),
('Suture Removal (SR)', 'general,oral_surgeon', 500.00, NULL, 'Suture Removal (SR).jpg',
 'Quick removal of stitches after a procedure.',
 'Stitches from an earlier extraction or surgery are removed once the area has healed, with a quick check of the site.'),
('TADS', 'orthodontist', 5000.00, 'unit', 'TADS.jpg',
 'Mini anchor screws that help move teeth during braces.',
 'Temporary anchorage devices are small titanium screws placed in the jaw to give braces a fixed anchor point for harder tooth movements. Charged per unit.'),
('Veneers', 'prosthodontist,general', 15000.00, 'tooth', 'veneers.jpg',
 'Thin shells that cover teeth for a brighter, even smile.',
 'Thin custom shells are bonded to the front of the teeth to improve their colour, shape and alignment. Charged per tooth.'),
('Whitening', 'general', 12000.00, NULL, 'Whitening.jpg',
 'Professional whitening for a brighter smile.',
 'An in-clinic whitening treatment that lightens stains and discolouration for a noticeably brighter smile.'),
('X-ray', 'general', 800.00, 'scan', 'xray.jpg',
 'Dental X-rays to see what the eye cannot.',
 'Dental X-ray images help the dentist find hidden decay, bone loss and other problems and plan your treatment. Charged per scan.');
-- Orthodontic services may be paid in installments (migration 069).
UPDATE services SET allows_installment = 1
WHERE service_name = 'New Ortho / Braces Installation';

-- Default system settings
INSERT INTO settings (setting_key, setting_value) VALUES
('reservation_fee', '500.00'),
('enable_receipt_validation', '1'),
('clinic_name',     'DGT One Dental Center - Bulacan'),
('clinic_open',     '10:00'),
('clinic_close',    '17:00'),
-- Bookable slots: 10AM 11AM 1PM 2PM 3PM 4PM (12PM is the lunch break). No 5PM
-- slot — the clinic closes at 17:00 and each slot is a 1-hour visit, so 4PM
-- is the last slot that finishes by closing time (migration 053).
('time_slots',      '10:00,11:15,13:15,14:15,15:15,16:15'),
-- Online payment instructions shown at the reservation-fee step (edit in admin Settings)
('pay_bank_details', 'BPI (Bank of the Philippine Islands)\nDGT One Dental Center\n4089-0512-77'),
('pay_gcash_name',   'DGT One Dental Center'),
('pay_gcash_number', '09764484252'),
('pay_gcash_qr',     'gcash_qrcode.jpg');

-- Sample promotions (one live clinic-wide, one draft service-specific)
INSERT INTO discounts (discount_name, discount_type, discount_value, target_scope, applied_service_ids, is_active) VALUES
('Back to School Promo', 'fixed', 350.00, 'all', NULL, 1),
('Preventive Care Promo (25% Off Cleaning and X-Ray)', 'percentage', 25.00, 'specific', '[5, 6, 10]', 0);

-- No seeded appointments / payments / counter transactions. A fresh import
-- starts with empty queues; real bookings arrive through the patient booking
-- flow and the front-desk walk-in flow.
