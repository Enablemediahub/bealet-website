-- Run once on existing hiring databases before deploying the updated admin page.
ALTER TABLE hiring_openings
    ADD COLUMN role_advantage VARCHAR(255) NULL AFTER candidate_preference;

UPDATE hiring_openings
SET role_advantage = 'Living in or near Madina or Adenta is an added advantage.'
WHERE slug = 'receptionist';
