-- 089: Minors as dependents of a parent / guardian account.
-- guardian_user_id: the parent / guardian whose account manages this patient
--   (a child added through walk-in "New patient" under 18, or online "For My Child").
--   The child's record has no e-mail and no usable login until they turn 18.
-- adult_account_at: when the child (now 18) was given their own username and
--   temporary password (the guardian is notified with them).
ALTER TABLE users
  ADD COLUMN guardian_user_id INT NULL DEFAULT NULL AFTER role,
  ADD COLUMN adult_account_at DATETIME NULL DEFAULT NULL AFTER guardian_user_id,
  ADD CONSTRAINT fk_users_guardian FOREIGN KEY (guardian_user_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- Backfill: login-less people booked "for someone else" (no e-mail, or the
-- booker's e-mail copied onto them) belong to the account that first booked them.
UPDATE users u
  JOIN (SELECT treated_patient_id t, MIN(patient_id) g FROM appointments
         WHERE booking_for = 'other' AND treated_patient_id IS NOT NULL GROUP BY treated_patient_id) x
    ON x.t = u.user_id
  JOIN users g ON g.user_id = x.g
   SET u.guardian_user_id = x.g
 WHERE u.role = 'patient' AND u.guardian_user_id IS NULL AND x.g <> u.user_id
   AND (u.email IS NULL OR u.email = '' OR u.email = g.email);

-- Anyone linked above who is already 18+ (or has no birthdate) is not handed a
-- surprise login now; only future 18th birthdays trigger the own-account notice.
UPDATE users u LEFT JOIN patient_records r ON r.patient_id = u.user_id
   SET u.adult_account_at = NOW()
 WHERE u.guardian_user_id IS NOT NULL
   AND (r.date_of_birth IS NULL OR r.date_of_birth <= DATE_SUB(CURDATE(), INTERVAL 18 YEAR));
