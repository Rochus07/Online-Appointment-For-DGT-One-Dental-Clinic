-- 034: Set the GCash QR image shown to patients at the reservation-fee step.
-- The file lives at uploads/payment/gcash_qrcode.jpg (committed with the repo).
UPDATE settings
SET setting_value = 'gcash_qrcode.jpg'
WHERE setting_key = 'pay_gcash_qr';
