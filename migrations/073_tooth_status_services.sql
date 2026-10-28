-- 073: tooth chart statuses follow the services (2026-10-18).
-- Old states: lc (light-cured) / cured / missing. New: resto / rct / missing.
-- Both old filled states were fillings, so they become resto.
UPDATE patient_records
SET tooth_chart = REPLACE(REPLACE(tooth_chart, '"lc"', '"resto"'), '"cured"', '"resto"')
WHERE tooth_chart LIKE '%"lc"%' OR tooth_chart LIKE '%"cured"%';
