# Bealet Laravel application

This is the new Laravel application being built alongside the existing PHP
website. The legacy site remains at the repository root; this application is
not yet a replacement. Its local MySQL database contains a development copy
of the locally configured legacy database, not production data.

## Requirements

- PHP 8.3 or later with the required Laravel extensions, including `pdo_mysql`
- Composer 2
- A dedicated, empty MySQL database for this application

## Local setup

1. From this directory, install dependencies with `composer install`.
2. Copy `.env.example` to `.env` if no local environment file exists.
3. Generate an application key with `php artisan key:generate`.
4. Configure the credentials for a **dedicated new database** in `.env`.
   The example targets `bealet_laravel`; do not point this app at the legacy
   `bealet_website` database.
5. Apply the schema to the new database with `php artisan migrate`.
6. Run `php artisan storage:link` to serve user-uploaded public images from
   Laravel's `storage/app/public` disk.
7. Configure `LEGACY_DB_*` with read-only credentials for the source database.
   Preview the transfer with `php artisan bealet:import-legacy-data`, check
   both database names and row counts, then explicitly import with
   `php artisan bealet:import-legacy-data --confirm`.
8. Run the test suite with `php artisan test`.

Do not use the generated Composer `setup` script against a database until the
database target has been verified: it runs migrations automatically.

The local preview uses file sessions/cache and a synchronous queue. The import
command requires distinct source and target databases, defaults to a read-only
preview, and only writes to the Laravel target when `--confirm` is supplied.
It upserts rows by primary key, verifies row contents and counts, and does not
delete target-only rows. Take a verified backup and use a staging copy before
importing production data.

## Accounts and checkout

The local application includes account registration/login, password resets,
customer profile and history, guest/account carts, checkout, inventory
reservations, and Paystack transaction verification. Configure
`PAYSTACK_SECRET_KEY` and a real mail transport only in the environment where
they are intended; the test suite uses mocked Paystack HTTP responses and does
not make live payment requests.

Expired unpaid checkout reservations are released by
`php artisan bealet:release-expired-checkout-reservations`. Production/staging
hosts must run Laravel's scheduler once per minute (`php artisan schedule:run`)
for the five-minute cleanup schedule to execute. The default local mail
transport is `log`, so password-reset emails are not delivered to customers
until an email provider is configured.

## Appointments and site content

The local app now supports appointment requests and account cancellation,
contact messages and admin inbox tracking, newsletter subscriptions and
unsubscribe confirmation, approved customer/product reviews, published blog
posts, staff/founder pages, and public branch information. Admin-only screens
cover appointment status, contact read/replied state, review moderation, and
blog drafts/publishing. Review and blog image uploads are limited to JPEG, PNG,
and WebP files up to 5 MB.

Set `CONTACT_NOTIFICATION_EMAIL` and configure a real mail transport to deliver
appointment/contact notifications. Until then, the default local mailer writes
messages to the application log.

## Admin operations and virtual try-on

The admin dashboard includes paid-order fulfillment and tracking history,
inventory updates, a customer directory, product/gallery/AR asset management,
site settings and image uploads, branches, staff, and founder gallery content.
Product, AR, branding, branch, staff, and gallery editing requires a
super-admin account; sub-admin accounts are limited to operational admin
screens. Products are deactivated rather than deleted so order history keeps
its references. Fulfillment cannot advance before verified payment, requires
a tracking number to ship, and cannot move backwards.

The `/ar-tryon` page uses camera permission and face tracking entirely in the
browser. It supports switching frames/cameras, front/side transparent PNG
assets, a GLB model viewer, and downloading a preview. Captures are not
uploaded. Browser camera access requires HTTPS or localhost and explicit
permission. MediaPipe and the GLB model-viewer library are loaded from pinned
CDN URLs; the page reports unsupported browsers, blocked permissions, missing
cameras, and asset-load failures. The eye-line angle is normalized for the
mirrored selfie preview so the overlay does not flip upside down. Adjust frame
size with the slider, mouse wheel, or two-finger pinch on the preview. Real
desktop/mobile camera validation is still required before staging sign-off.

Admin image files are content-validated and size-limited; GLB uploads must use
the `.glb` extension and binary glTF signature. Files are stored with generated
names on Laravel's public disk and require `php artisan storage:link`.
Performance, accessibility, backup/restore, and staging verification remain
part of Migration 10. The current local Composer audit reported no known
dependency advisories.

## Production cutover status

Migration 11's gated cutover and rollback runbook is in
[`LARAVEL-MIGRATION-PLAN.md`](../LARAVEL-MIGRATION-PLAN.md). Production is not
connected or deployed from this workspace: the production target, verified
backup/restore, staging approval, and authorized cutover window have not been
provided. Do not run a production import or change DNS until the runbook
prerequisites and owner approval are complete.

The legacy-compatible `/api/subscribe-newsletter` endpoint is rate-limited and
returns the expected JSON success/error shape. Its public subscription action
does not require a CSRF token; rate limiting and email validation are applied.

## Current status

The Laravel framework scaffold, initial schema/models, local data import, and
storefront, customer-account, cart, checkout, and mocked Paystack payment flows
as well as appointments, contact, newsletter, reviews, blog, and site-content
pages are in place locally. Full feature parity, production integrations, and
staging deployment remain future migration work. See the repository's
[`LARAVEL-MIGRATION-PLAN.md`](../LARAVEL-MIGRATION-PLAN.md) for phase tracking.
