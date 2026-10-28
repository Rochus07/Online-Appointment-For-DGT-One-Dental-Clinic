-- Adds a short second, factual sentence to each active service's description
-- so the landing-page service grid (first sentence only) and its detail
-- popup (full description) show genuinely different text instead of one
-- sentence truncated with an ellipsis. Data-only, no schema change.

UPDATE services SET description = 'Repairs and rebuilds teeth damaged by decay, wear or trauma using fillings, inlays, onlays, crowns and bridges to restore normal function and appearance. Materials are matched closely to your natural tooth colour, so the repair blends in and holds up to everyday chewing.' WHERE service_name = 'Restorative Dentistry';

UPDATE services SET description = 'Replaces missing teeth and restores the bite with removable and fixed prostheses, including complete and partial dentures, bridges and implant-supported restorations. Each prosthesis is custom-fitted to your mouth for comfortable chewing, clear speech and a natural-looking smile.' WHERE service_name = 'Prosthodontics';

UPDATE services SET description = 'Corrects crooked teeth and misaligned bites with braces and clear aligners, gradually guiding the teeth and jaws into their ideal positions. Regular adjustment visits track your progress and keep treatment moving toward a healthier, more even bite.' WHERE service_name = 'Orthodontics';

UPDATE services SET description = 'Permanently replaces missing teeth with a titanium post placed in the jawbone and topped with a custom crown, preserving bone and restoring full chewing strength. Once healed, an implant looks, feels and functions like a natural tooth and can last for decades with regular care.' WHERE service_name = 'Dental Implants';

UPDATE services SET description = 'Gentle, child-focused dental care, including first visits, cleanings, fluoride, sealants and fillings, that builds healthy habits and a positive attitude toward the dentist. Every visit is paced to the child''s comfort, with clear explanations that make dental care feel safe and familiar.' WHERE service_name = 'Pedodontics';

UPDATE services SET description = 'Routine preventive and diagnostic care, including check-ups, professional cleaning, fillings and oral-health advice, to keep your teeth and gums healthy year round. Regular visits catch small issues early, before they turn into bigger, more costly problems.' WHERE service_name = 'General Dentistry';

UPDATE services SET description = 'Improves the look of your smile through teeth whitening, veneers, cosmetic bonding and tooth-coloured restorations for a brighter, more even appearance. Your dentist will recommend the combination of treatments that best suits your teeth and the smile you want.' WHERE service_name = 'Cosmetic Dentistry';

UPDATE services SET description = 'Surgical treatment of the teeth, gums and jaw, including simple and surgical extractions, impacted wisdom teeth and minor oral surgery, performed under local anaesthesia. Aftercare instructions and follow-up checks are provided to support a smooth, comfortable recovery.' WHERE service_name = 'Dental Surgery';

UPDATE services SET description = 'Saves badly infected or damaged teeth with root canal treatment: removing the inflamed pulp, disinfecting the canals and sealing them to stop further infection. The treated tooth is then sealed and typically restored with a crown to protect it for long-term use.' WHERE service_name = 'Endodontics';

UPDATE services SET description = 'Diagnostic dental imaging, including panoramic X-rays for a full view of the jaws and periapical X-rays for detail on individual teeth and roots, to support accurate diagnosis and treatment planning. Images are reviewed with you so you understand what they show before any treatment is planned.' WHERE service_name = 'Dental X-Ray';
