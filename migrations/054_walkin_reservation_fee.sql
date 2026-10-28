-- 054: Allow the front desk to log a Cash reservation fee / downpayment on a
-- walk-in booking, the same way an online booking's reservation fee already
-- works (payments.reservation_fee_paid, deducted at checkout). Online only
-- ever used 'bank'/'gcash'/'paymaya'; a walk-in patient pays the receptionist
-- directly, so 'cash' is added as a fourth option.
ALTER TABLE payments
    MODIFY reservation_method ENUM('cash', 'bank', 'gcash', 'paymaya') DEFAULT NULL;
