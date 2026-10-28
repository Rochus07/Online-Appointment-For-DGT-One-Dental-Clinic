-- 063: admin toggle for requiring a payment screenshot on online bookings.
-- enable_receipt_validation = '1' (default): GCash / Bank Transfer online
-- bookings must attach a receipt screenshot plus the reference number.
-- '0': only the reference number is required; the screenshot is optional.
INSERT INTO settings (setting_key, setting_value) VALUES ('enable_receipt_validation', '1')
  ON DUPLICATE KEY UPDATE setting_key = setting_key;
