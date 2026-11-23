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
must also be writable by PHP.

Applicant confirmations and new-application alerts use SMTP when credentials
are configured in `../bealet-website-private/.env`, outside the public web root.
Run `composer install`, then initialize the private configuration:

```sh
mkdir -p ../bealet-website-private
cp .env.example ../bealet-website-private/.env
chmod 600 ../bealet-website-private/.env
```

Set the Gmail sender, hiring notification inbox, and Gmail App Password there.
For Gmail SMTP, enable 2-Step Verification and create an App Password; never
use or commit your regular Gmail password. Without SMTP credentials, the app
falls back to PHP `mail()`, which depends on the local or hosting mail
transport.
