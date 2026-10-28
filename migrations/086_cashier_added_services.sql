-- 086: services the cashier added at checkout (2026-10-21).
-- added_by = the cashier/admin who attached the line on the Billing screen;
-- NULL = logged by the dentist (every older row).
ALTER TABLE appointment_services ADD COLUMN added_by INT NULL AFTER price;
