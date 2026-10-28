-- 037: One-time live-DB cleanup — remove every demo / test patient and the
-- fixture data attached to them. The matching seed rows are also being deleted
-- from schema.sql (patient.demo account + the "Patient One / Marco Reyes /
-- Maria Santos" sample bookings + demo counter transactions), and the
-- seed_demo_revenue / seed_demo_dashboard / seed_demo_activity_log /
-- seed_demo_pending_bill generator scripts are being removed, so a fresh import
-- starts with zero patients and empty queues. There is no schema.sql counterpart
-- for this file — it only cleans an already-running database.
USE dental_clinic_system;

-- Activity-log rows for the demo patients (FK is ON DELETE SET NULL, so the
-- user delete alone would orphan them — remove explicitly).
DELETE FROM activity_log
WHERE user_id IN (SELECT user_id FROM users WHERE username IN ('patient.demo', 'rexmicole.carbonel'));

-- Stale sample bookings — these point at the old "Patient One" patient_id 6,
-- which later became the dental-assistant account. Cascades to
-- appointment_services / payments / invoice_items.
DELETE a FROM appointments a
JOIN users u ON u.user_id = a.patient_id
WHERE u.role <> 'patient';

-- Demo over-the-counter transactions seeded by schema.sql.
DELETE FROM counter_transactions
WHERE description IN ('Walk-in dental consultation', 'Manual adjustment - overpayment refund reversal');

-- The demo / test patient accounts themselves. ON DELETE CASCADE clears their
-- remaining appointments, appointment_services, payments, invoice_items,
-- notifications and patient_records.
DELETE FROM users WHERE username IN ('patient.demo', 'rexmicole.carbonel');

-- Test "payment complete" notifications that were sent to the old Patient One
-- account (now the dental-assistant user) during billing tests. Notifications
-- are a patient-only feature, so any row for a non-patient is test noise.
DELETE n FROM notifications n
JOIN users u ON u.user_id = n.user_id
WHERE u.role <> 'patient';

-- Test dentist account (the developer's own name / e-mail, marked owner, never
-- used — zero appointments / schedule / activity). elaine.silva is the seeded
-- owner dentist.
DELETE FROM users WHERE username = 'rex.carbonel';

-- The audit trail up to now is entirely local development / testing activity
-- (every row ip = '::1', many referencing the demo patients just removed).
-- Clear it so the History Log starts clean; it refills from real signed-in use.
DELETE FROM activity_log;
