-- 074: show each service's usual duration to patients (2026-10-18).
-- services.duration_minutes already existed (unused since 2026-09); these are
-- starting estimates the admin can change on the Services page. For a
-- per-unit service the time is per unit (per tooth, per arch, ...).
UPDATE services SET duration_minutes = CASE service_name
  WHEN 'Adjustment' THEN 30
  WHEN 'Biteplane Impression / Installation' THEN 30
  WHEN 'Braces Removal' THEN 60
  WHEN 'Bridge' THEN 60
  WHEN 'Consult' THEN 30
  WHEN 'Dentures' THEN 60
  WHEN 'Extraction (Bunot)' THEN 30
  WHEN 'Frenectomy' THEN 45
  WHEN 'Gingivectomy' THEN 60
  WHEN 'New Ortho / Braces Installation' THEN 90
  WHEN 'Oral Prophylaxis (Cleaning)' THEN 45
  WHEN 'RB/Wire' THEN 30
  WHEN 'Resto (Pasta)' THEN 30
  WHEN 'Retainer Impression / Installation' THEN 30
  WHEN 'Root Canal Treatment (RCT)' THEN 60
  WHEN 'Surgery' THEN 60
  WHEN 'Suture Removal (SR)' THEN 15
  WHEN 'TADS' THEN 30
  WHEN 'Veneers' THEN 60
  WHEN 'Whitening' THEN 60
  WHEN 'X-ray' THEN 15
  ELSE duration_minutes END;
