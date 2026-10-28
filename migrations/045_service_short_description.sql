-- Adds a short, independently-written homepage card blurb per service,
-- separate from the full `description` (which now only ever renders in the
-- landing-page service-detail popup). Needed because a "first sentence of
-- description" split (migration 044) still meant the card text was a literal
-- prefix of the modal text; the reference the user is matching uses two
-- completely independent pieces of copy.

ALTER TABLE services
  ADD COLUMN short_description VARCHAR(180) NULL AFTER description;

UPDATE services SET short_description = 'Repairs and rebuilds damaged teeth to restore comfortable, natural function.' WHERE service_name = 'Restorative Dentistry';
UPDATE services SET short_description = 'Custom dentures, bridges and implant restorations that bring back a complete smile.' WHERE service_name = 'Prosthodontics';
UPDATE services SET short_description = 'Braces and clear aligners that gradually straighten teeth and align the bite.' WHERE service_name = 'Orthodontics';
UPDATE services SET short_description = 'A titanium post and custom crown that permanently replace a missing tooth.' WHERE service_name = 'Dental Implants';
UPDATE services SET short_description = 'Gentle, child-focused dental visits that build lifelong healthy habits.' WHERE service_name = 'Pedodontics';
UPDATE services SET short_description = 'Routine check-ups and cleanings that keep your teeth and gums healthy year round.' WHERE service_name = 'General Dentistry';
UPDATE services SET short_description = 'Whitening, veneers and bonding for a brighter, more even smile.' WHERE service_name = 'Cosmetic Dentistry';
UPDATE services SET short_description = 'Extractions and minor oral surgery performed safely under local anaesthesia.' WHERE service_name = 'Dental Surgery';
UPDATE services SET short_description = 'Root canal treatment that saves a badly infected or damaged tooth.' WHERE service_name = 'Endodontics';
UPDATE services SET short_description = 'Panoramic and periapical imaging that supports an accurate diagnosis.' WHERE service_name = 'Dental X-Ray';
