-- 061: Finalized clinic service list + per-unit quantity billing (2026-10).
--
-- services.quantity_unit: NULL = a single-session service (always quantity 1);
-- otherwise the unit the price is charged per ('tooth', 'unit', 'arch',
-- 'quadrant', 'tooth surface', 'scan') and the dentist / cashier may raise the
-- quantity during the visit.
--
-- appointment_services.qty: how many units the dentist logged. `price` stays the
-- LINE TOTAL (qty x unit price), so every existing SUM(price) report is unchanged.
--
-- The old 10 services were deleted by hand on 2026-10-02 before this ran.

ALTER TABLE services
  ADD COLUMN quantity_unit VARCHAR(40) DEFAULT NULL AFTER base_price;

ALTER TABLE appointment_services
  ADD COLUMN qty INT NOT NULL DEFAULT 1 AFTER service_id;

INSERT INTO services (service_name, specialties, base_price, quantity_unit, image, short_description, description) VALUES
('Adjustment', 'orthodontist', 1000.00, NULL, 'Adjustment.jpg',
 'Routine tightening and check of your braces.',
 'A regular orthodontic visit where the dentist checks your progress and adjusts the wires and brackets so your teeth keep moving into place.'),
('Biteplane Impression / Installation', 'orthodontist', 5000.00, NULL, 'Biteplane Impression_Installition.jpg',
 'A custom biteplane to correct your bite and protect your teeth.',
 'Impressions are taken to make a removable biteplane appliance, which is then fitted to help open or correct the bite and guard teeth against grinding.'),
('Braces Removal', 'orthodontist', 3500.00, NULL, 'Braces Removal.jpg',
 'Safe removal of brackets and wires once treatment is done.',
 'Brackets, wires and leftover bonding material are carefully removed and the teeth are polished, ready for your retainer.'),
('Bridge', 'prosthodontist', 15000.00, 'unit', 'bridge.jpg',
 'A fixed bridge that fills the gap left by missing teeth.',
 'A fixed prosthesis anchored to the neighbouring teeth replaces one or more missing teeth, restoring your bite and smile. Charged per unit.'),
('Consult', 'general', 500.00, NULL, 'Consult.jpg',
 'A check-up and talk with the dentist about your concern.',
 'An examination and consultation where the dentist looks at your concern, explains your options and recommends a treatment plan.'),
('Dentures', 'prosthodontist', 12000.00, 'arch', 'dentures.jpg',
 'Removable replacement teeth made to fit your mouth.',
 'Custom removable dentures replace missing teeth on the upper or lower jaw, helping you chew and smile with confidence. Charged per arch.'),
('Extraction (Bunot)', 'general,oral_surgeon', 1500.00, 'tooth', 'Extraction (Bunot).jpg',
 'Gentle removal of a damaged or problem tooth.',
 'A tooth that is badly decayed, broken or causing problems is removed under local anaesthesia, with aftercare instructions for healing. Charged per tooth.'),
('Frenectomy', 'oral_surgeon,periodontist', 7000.00, NULL, 'Frenectomy.jpg',
 'Releases a tight lip or tongue tie.',
 'A minor procedure that trims or releases a tight frenum (the tissue under the lip or tongue) to improve movement, speech or gum health.'),
('Gingivectomy', 'periodontist', 8000.00, 'quadrant', 'Gingivectomy.jpg',
 'Reshapes overgrown or diseased gum tissue.',
 'Excess or unhealthy gum tissue is removed and reshaped to treat gum disease or improve the look of your smile. Charged per quadrant.'),
('New Ortho / Braces Installation', 'orthodontist', 45000.00, NULL, 'New Ortho  Braces Installation.jpg',
 'Start your braces treatment for a straighter smile.',
 'Brackets and wires are bonded to your teeth to begin orthodontic treatment that gradually straightens teeth and corrects the bite.'),
('Oral Prophylaxis (Cleaning)', 'general,periodontist', 1500.00, NULL, 'Oral Prophylaxis (Cleaning).jpg',
 'Professional cleaning to remove plaque and tartar.',
 'Plaque and tartar are scaled away and the teeth are polished, helping prevent cavities and gum disease.'),
('RB/Wire', 'orthodontist', 1200.00, NULL, 'RBWirewebp.jpg',
 'Replacement rubber bands or wire for your braces.',
 'Worn or broken rubber bands or an orthodontic wire are replaced so your braces keep working as planned.'),
('Resto (Pasta)', 'general', 1500.00, 'tooth surface', 'Resto (Pasta).jpg',
 'A tooth-coloured filling to repair a cavity.',
 'Decay is removed and the tooth is restored with a tooth-coloured filling that blends with your natural teeth. Charged per tooth surface.'),
('Retainer Impression / Installation', 'orthodontist', 6000.00, NULL, 'Retainer Impression Installation.jpg',
 'A custom retainer to keep your teeth in place.',
 'Impressions are taken to make a retainer that holds your teeth in their new position after orthodontic treatment, then it is fitted and adjusted.'),
('Root Canal Treatment (RCT)', 'endodontist', 8000.00, 'tooth', 'Root Canal Treatment (RCT).jpg',
 'Saves a badly infected or damaged tooth.',
 'The infected pulp inside the tooth is removed, the canals are cleaned and sealed, and the tooth is prepared for a final restoration. Charged per tooth.'),
('Surgery', 'oral_surgeon', 10000.00, NULL, 'Surgery.jpg',
 'Oral surgery such as impacted tooth removal.',
 'Surgical procedures such as removing impacted wisdom teeth, performed with local anaesthesia and full aftercare guidance.'),
('Suture Removal (SR)', 'general,oral_surgeon', 500.00, NULL, 'Suture Removal (SR).jpg',
 'Quick removal of stitches after a procedure.',
 'Stitches from an earlier extraction or surgery are removed once the area has healed, with a quick check of the site.'),
('TADS', 'orthodontist', 5000.00, 'unit', 'TADS.jpg',
 'Mini anchor screws that help move teeth during braces.',
 'Temporary anchorage devices are small titanium screws placed in the jaw to give braces a fixed anchor point for harder tooth movements. Charged per unit.'),
('Veneers', 'prosthodontist,general', 15000.00, 'tooth', 'veneers.jpg',
 'Thin shells that cover teeth for a brighter, even smile.',
 'Thin custom shells are bonded to the front of the teeth to improve their colour, shape and alignment. Charged per tooth.'),
('Whitening', 'general', 12000.00, NULL, 'Whitening.jpg',
 'Professional whitening for a brighter smile.',
 'An in-clinic whitening treatment that lightens stains and discolouration for a noticeably brighter smile.'),
('X-ray', 'general', 800.00, 'scan', 'xray.jpg',
 'Dental X-rays to see what the eye cannot.',
 'Dental X-ray images help the dentist find hidden decay, bone loss and other problems and plan your treatment. Charged per scan.');
