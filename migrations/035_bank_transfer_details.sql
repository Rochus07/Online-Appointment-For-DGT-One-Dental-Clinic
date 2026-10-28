-- 035: Fill in the clinic's bank-transfer details shown to patients at the
-- reservation-fee step (was the placeholder "Bank name / Account name / Account number").
-- Three lines: bank, account name, account number. Replace with the real account
-- from admin once opened.
UPDATE settings
SET setting_value = 'BPI (Bank of the Philippine Islands)\nDGT One Dental Center\n4089-0512-77'
WHERE setting_key = 'pay_bank_details';
