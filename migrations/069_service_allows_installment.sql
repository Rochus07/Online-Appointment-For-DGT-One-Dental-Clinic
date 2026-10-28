-- 069: installment payments are only for orthodontic (braces) treatments.
-- services.allows_installment = 1 lets the cashier choose "Installment" for a
-- bill that includes the service; every other bill is Full Payment only.
ALTER TABLE services
  ADD COLUMN allows_installment TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity_unit;

UPDATE services SET allows_installment = 1
WHERE service_name = 'New Ortho / Braces Installation';
