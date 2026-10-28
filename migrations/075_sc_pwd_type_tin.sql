-- 075: senior / PWD discount details (2026-10-18).
-- sc_pwd_type: which discount the cashier applied (senior = 60+ only).
-- sc_tin: the senior citizen's TIN (optional, 000-000-000[-000|-00000]).
-- sc_pwd_id keeps the OSCA ID (senior) or the PWD ID (RR-PPMM-BBB-NNNNNNN).
ALTER TABLE payments
  ADD COLUMN sc_pwd_type ENUM('senior','pwd') DEFAULT NULL AFTER sc_pwd_id,
  ADD COLUMN sc_tin VARCHAR(20) DEFAULT NULL AFTER sc_pwd_type;
UPDATE payments SET sc_pwd_type = 'senior' WHERE sc_pwd_discount = 1 AND sc_pwd_type IS NULL;
