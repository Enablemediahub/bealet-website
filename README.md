# bealet-website
The official website for Bealet Optical.

## Hiring portal

The public careers page is available at `/hiring` (and `/hiring.php`).
Before deploying, run [`sql/hiring_portal.sql`](sql/hiring_portal.sql) against
the site's database. It creates the hiring tables and seeds the six default
roles as closed, and is safe to run more than once.

Super admins manage role details, application limits, and applications from
**Admin > Hiring**. The limit counts received applications; reaching it
automatically closes that role. From the same admin page, upload a hero image
and an optional image for each role card (JPEG, PNG, or WebP, up to 5 MB).
Admins can also add an optional highlighted advantage note to each role. For
an existing database, run [`sql/hiring_role_advantage.sql`](sql/hiring_role_advantage.sql)
once before deploying the updated hiring admin page.
Images are stored under `assets/uploads/hiring`; ensure this directory is
writable by PHP. CVs are stored outside the public web root in a
`bealet-website-private/hiring-cvs` directory above the project folder, which
must also be writable by PHP. Applicant confirmations use the site's existing
PHP `mail()` setup; configure and verify mail delivery before opening a role.
