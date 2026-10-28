-- Phase 1: patient booking now captures the requested service, insurance and the
-- reservation-fee payment (method, reference number, uploaded receipt); the
-- receptionist can decline a request with a reason.
-- Safe to run once against an existing dental_clinic_system database.
USE dental_clinic_system;

ALTER TABLE appointments
    ADD COLUMN service_id         INT           DEFAULT NULL AFTER dentist_id,
    ADD COLUMN insurance_provider VARCHAR(120)  DEFAULT NULL AFTER relationship,
    ADD COLUMN decline_reason     VARCHAR(255)  DEFAULT NULL AFTER notes,
    ADD CONSTRAINT fk_appt_service FOREIGN KEY (service_id)
        REFERENCES services(service_id) ON DELETE SET NULL;

ALTER TABLE payments
    ADD COLUMN reservation_method    ENUM('bank','gcash','paymaya') DEFAULT NULL AFTER reservation_fee_paid,
    ADD COLUMN reservation_reference VARCHAR(40)  DEFAULT NULL AFTER reservation_method,
    ADD COLUMN reservation_receipt   VARCHAR(255) DEFAULT NULL AFTER reservation_reference;
