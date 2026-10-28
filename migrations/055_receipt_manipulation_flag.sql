-- ============================================================
--  Payment receipt image-manipulation flag
--  Adds a server-side metadata check on uploaded GCash/bank
--  receipt screenshots: if the file's metadata contains an
--  image-editing-software signature (Photoshop, Canva, GIMP,
--  etc.), the booking's reservation payment is flagged so the
--  receptionist can double-check it before approving.
-- ============================================================

ALTER TABLE payments
    ADD COLUMN receipt_flag_suspected TINYINT(1) NOT NULL DEFAULT 0 AFTER reservation_receipt,
    ADD COLUMN receipt_flag_reason    VARCHAR(255) NULL AFTER receipt_flag_suspected;
