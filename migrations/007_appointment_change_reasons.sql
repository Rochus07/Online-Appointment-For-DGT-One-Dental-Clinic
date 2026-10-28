-- Capture the reason a booking was cancelled or rescheduled, and remember the
-- date an appointment was moved from. Patients supply these when they cancel or
-- request a reschedule from the portal; staff supply them from the reschedule
-- modal. Shown in the Full Details modal on every dashboard.
-- Safe to run once against an existing dental_clinic_system database.
USE dental_clinic_system;

ALTER TABLE appointments
    ADD COLUMN cancellation_reason     VARCHAR(255) DEFAULT NULL AFTER decline_reason,
    ADD COLUMN reschedule_reason       VARCHAR(255) DEFAULT NULL AFTER cancellation_reason,
    ADD COLUMN previous_scheduled_date DATE         DEFAULT NULL AFTER reschedule_reason;
