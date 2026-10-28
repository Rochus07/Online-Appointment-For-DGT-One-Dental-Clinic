-- Points Dental X-Ray at its new uploaded photo (a real panoramic X-ray,
-- uploads/services/dental_xray.jpg). Endodontics keeps its existing filename
-- (endodontics.jpg) since the user replaced that file's contents directly
-- rather than uploading under a new name — no row update needed for it.

UPDATE services SET image = 'dental_xray.jpg' WHERE service_name = 'Dental X-Ray';
