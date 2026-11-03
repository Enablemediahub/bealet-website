# Bealet Website: Laravel + MySQL Migration Plan

## Goal

Rebuild the existing Bealet Optical Center website as a maintainable Laravel
application while retaining MySQL, existing customer and business data, the
current public experience, and the admin workflows.

This is a phased application migration, not a database reset. Do not run the
legacy `sql/database.sql` against production: it drops tables and contains
sample data. The live schema must be inspected and backed up before designing
the Laravel migrations.

## Target shape

- **Application:** A supported Laravel release compatible with the hosting
  environment and PHP version.
- **Database:** MySQL, with Laravel migrations and Eloquent models.
- **Views and frontend:** Blade templates; retain the current CSS and JavaScript
  initially, then modernize separately if desired.
- **Operations:** Environment-based configuration, managed secrets, scheduled
  backups, application logging, and documented deployment/rollback steps.
- **Integrations:** Paystack, email, SMS, and browser camera/AR functionality
  behind clearly separated services or adapters.

## Current progress

- **Migration 1 — Local inventory complete; production baseline outstanding.**
  The repository and local environment have been inventoried. There are 34
  public PHP pages, 20 admin pages, 9 API endpoints, and 82 tracked files under
  `assets/uploads/`. The local runtime is PHP 8.4.27 with Composer 2.10.3 and
  PDO MySQL; a MySQL command-line client is not installed.
- A read-only connection to the configured local database found 24 tables:
  `appointments`, `blog_posts`, `cart`, `company_branches`, `contacts`,
  `customer_reviews`, `founder_gallery`, `gamehub_attempts`, `gamehub_players`,
  `gamehub_questions`, `gamehub_settings`, `newsletter`, `order_items`,
  `order_prescriptions`, `orders`, `prescription_details`, `product_images`,
  `products`, `reviews`, `site_settings`, `staff_members`, `tracking_history`,
  `users`, and `wishlist`. The local schema differs from `sql/database.sql`.
  The `gamehub_*` tables have no matching PHP references in the repository;
  they were included in the local schema pending an owner/use decision.
- Production schema, hosting constraints, representative production journeys,
  and a verified production backup/restore have **not** been confirmed. No
  production data was changed or copied.
- **Migration 2 — Local scaffold complete; staging readiness outstanding.**
  Laravel 13.39.0 is under `laravel/` with PHP 8.3+ requirements. The local
  `.env` is ignored by Git and targets a separate `bealet_laravel` database;
  staging deployment and production runtime compatibility remain unverified.
- **Migration 3 — Local schema and import complete.** Laravel migrations now
  define the observed 24 legacy tables, and Eloquent models map those tables.
  A guarded `bealet:import-legacy-data` command previews by default, requires
  `--confirm` to write to a distinct target, upserts on IDs, and checks row
  contents and counts. The new local database contains a verified copy of the
  local legacy database. Production data has not been imported.
- **Migration 4 — Initial storefront/catalog port complete locally.** The
  Laravel homepage and shared header/footer are in Blade. The shop supports
  search, category/audience/brand/price/AR-ready filters, sorting, pagination,
  product details, galleries, and the legacy `shop.php?view_product=` redirect.
  The shared footer and shop filter/sidebar/product-card layout have since
  been aligned more closely with the legacy design. Local legacy assets are
  exposed through a relative symlink. This remains an initial port, not full
  visual or feature parity with the PHP storefront.
- **Migration 5 — Customer account flows implemented locally.** Registration,
  login/logout, password reset, profile/password updates, customer order access,
  linked appointment history, and an admin-only route gate are in place.
  Existing `password_hash` values are retained for Laravel authentication.
  Granular sub-admin permissions remain part of the later admin migration;
  password-reset email requires a configured mail provider.
- **Migration 6 — Cart and payment flows implemented locally.** Guest and
  signed-in carts, stock-checked checkout, order/item snapshots, optional
  manual prescriptions, expiring inventory reservations, Paystack initialize
  and server-side verification, idempotent success handling, and failed/
  abandoned/expired reservation release are implemented. Paystack is tested
  with HTTP fakes only; no live payment or production data was used. A host
  scheduler must run `php artisan schedule:run` for automatic expiry cleanup.
- The Laravel suite at the end of Migration 6 passed 16 tests, including
  account authorization, checkout/inventory, payment callback, and
  reservation-expiry coverage.
  Local/staging deployment, email delivery, live Paystack credentials, and
  production data/backup checks remain outstanding.
- **Migration 7 — Appointments and customer communications implemented
  locally.** Appointment requests validate available dates and time slots,
  persist customer association, and allow customers to cancel their own
  pending bookings. A row-lock reservation table prevents concurrent bookings
  of the same slot. Contact submissions, admin inbox state, newsletter
  subscribe/reactivate/unsubscribe, and logged appointment/contact emails are
  included. Email delivery still requires a real mail transport.
- **Migration 8 — Reviews, blog, and public site content implemented locally.**
  Public pages now show approved customer/product reviews, published blog
  posts, staff and founder/gallery content, branch contact details, and
  database-backed hero settings. Review submission/moderation and admin blog
  draft/publish/edit/delete flows are included, with validated image uploads
  stored on Laravel's public disk. Existing appointment/contact/review/blog/
  team URLs redirect into Laravel. Site-setting, staff, and gallery admin CRUD
  was completed locally in Migration 9.
- The current Laravel suite passes 30 tests, covering appointment validation
  and ownership, communications, newsletter state, content visibility,
  review moderation/uploads, and blog publishing. The appointment lock-table
  migration is applied only to the local `bealet_laravel` database.
- **Migration 9 — Local admin and AR workflows implemented.** Super admins
  can manage products, product/gallery images, validated 2D and GLB try-on
  assets, AR positioning, staff, founder gallery, site settings, and branches.
  Admins can update inventory, inspect customers, and advance paid orders
  through tracked fulfillment. Product/settings CRUD is super-admin-only;
  sub-admins retain operational access. The browser try-on uses local
  MediaPipe face tracking, supports frame switching, camera selection, and
  preview download; camera processing remains client-side.
- **Migration 10 — Local verification in progress.** The current suite passes
  37 tests / 218 assertions, including admin role boundaries, upload checks,
  order fulfillment, inventory, site content, and AR page routing. Pint,
  Composer manifest validation and audit (no known dependency advisories),
  JavaScript syntax, route registration, and whitespace checks pass.
  The owner reports that staging sign-off and backup/restore verification are
  complete; evidence has not been independently reviewed in this workspace.
  Production browser/device testing, accessibility and performance review
  remain outstanding.
- **Migration 11 — Production cutover not executed.** A safe local cutover
  runbook is documented below. The owner has identified `www.bealet.com`,
  authorized a cutover, and reports staging sign-off and backup/restore
  verification complete. Those reports have not been independently verified
  in this workspace. The existing website is hosted on Hostinger, but its
  hosting plan and Laravel deployment capabilities have not been checked.
  The cutover window is not scheduled, and no production resources have been
  provisioned; traffic and data have not been changed.
- **Hosting readiness:** First check whether the current Hostinger plan
  supports this Laravel app's PHP 8.3+ requirement, Composer-based deployment,
  MySQL, required PHP extensions, secure environment variables, writable
  storage, public storage links, HTTPS, and scheduled commands. Verify that
  backups can be restored. If the current plan cannot meet these requirements,
  compare a suitable Hostinger upgrade/VPS with other managed Laravel hosts
  before choosing a destination. Do not assume Laravel Cloud is selected.

## Migration stages

### Migration 1 — Inventory and baseline

- Inspect the live MySQL schema, data volumes, constraints, indexes, and any
  schema changes made outside `sql/database.sql`.
- Inventory every public URL, admin action, API endpoint, scheduled process,
  upload directory, external integration, and current redirect behavior.
- Record representative current behavior and outputs for critical journeys:
  browse product, sign in, book appointment, checkout, pay, track order, and
  administer products/orders.
- Identify the production PHP/Laravel-compatible runtime and deployment
  constraints.
- Produce a verified database backup and an asset-file inventory before any
  production changes.

**Exit check:** An agreed feature inventory, schema snapshot, backup restore
test, and deployment approach exist.

### Migration 2 — Laravel foundation

- Create the Laravel project, configure local/test/staging environments, and
  connect it to MySQL through environment variables.
- Establish route, controller, request validation, service, model, policy,
  Blade, logging, and error-handling conventions.
- Add health checks, session configuration, CSRF protection, secure cookie
  settings, and a deployment-ready configuration.
- Keep secrets out of source control; rotate any credentials that have been
  exposed in application files and provision replacements through the
  deployment environment.
- Set up automated tests and a staging deployment before porting features.

**Exit check:** The app deploys to staging, connects to a non-production MySQL
database, and passes its initial automated checks.

### Migration 3 — Schema and data compatibility

- Define Laravel migrations and Eloquent models for the existing business
  entities, including users, products, orders, order items, prescriptions,
  appointments, carts, wishlists, reviews, blog posts, staff, contacts,
  newsletter subscribers, and site/admin settings.
- Reconcile differences between the checked-in SQL schema and the actual
  running application schema. Include newer fields and tables discovered in
  Migration 1, such as payment, product AR, review approval, and site-content
  fields where present.
- Preserve important IDs, timestamps, unique constraints, indexes, and foreign
  key relationships where practical; document any intentional schema changes.
- Write an idempotent import/verification process for the existing MySQL data.
  Do not import sample records as production data.
- Verify record counts, key relationships, monetary totals, order statuses,
  and representative records before and after import.
- Check password hash compatibility. Preserve hashes only when Laravel can
  verify them safely; otherwise require a secure password reset rather than
  storing or exporting plaintext passwords.

**Exit check:** A staging import can be repeated safely and passes documented
data-integrity checks.

### Migration 4 — Shared storefront and catalog

- Port the shared header, footer, navigation, branding, SEO metadata, flash
  messages, and responsive layout to Blade.
- Implement product listing, detail views, categories, search, filters,
  sorting, pagination, featured products, and product images.
- Preserve useful legacy URLs and define redirects/canonical URLs where route
  names change.
- Keep the existing CSS and browser-side behavior where possible to avoid
  changing design and interaction at the same time as the backend.

**Exit check:** Catalog pages match the current behavior on desktop and mobile,
including empty, filtered, and paginated states.

### Migration 5 — Customer accounts and profile

- Port registration, login/logout, password reset, profile updates, account
  status, and customer order/appointment history.
- Replace legacy session logic with Laravel authentication and authorization.
- Migrate admin role behavior into explicit middleware, policies, and
  permissions; verify that sub-admin restrictions are enforced server-side.
- Preserve customer accounts and provide a safe password reset path for
  incompatible hashes.

**Exit check:** Customer and admin authentication, session expiry, password
reset, and authorization tests pass.

**Local implementation note:** The customer account screens and basic admin
gate are implemented and covered by tests. Appointment booking/history are
covered under Migration 7, and granular admin-role permissions remain for
Migration 9.

### Migration 6 — Cart, checkout, orders, and payment

- Implement guest and signed-in carts, quantity/stock validation, shipping
  details, totals, tax calculation, prescriptions, and order creation.
- Make order creation and stock changes transactional and prevent duplicate
  payment callbacks from creating duplicate effects.
- Integrate Paystack using environment-managed credentials and server-side
  transaction verification. Never treat a browser redirect alone as proof of
  payment.
- Port order confirmation, tracking codes, order status updates, customer
  emails, and applicable SMS notifications.
- Preserve order history and payment references; reconcile legacy pending and
  paid orders carefully.

**Exit check:** Staging tests cover successful, failed, abandoned, and repeated
payment notifications, plus inventory and order-history integrity.

**Local implementation note:** Core guest/account checkout and the Paystack
callback are implemented and locally tested with mocked gateway responses.
Configure production mail/payment services and the scheduler, then run the
staging integration checks before this migration is considered production
ready.

### Migration 7 — Appointments and customer communications

- Port appointment booking, date/time rules, customer association, and admin
  status management.
- Port contact submissions, newsletter signup, and customer-facing
  confirmation/notification messages.
- Validate and escape content at the appropriate boundaries; deliver email and
  SMS through configured services with explicit error logging.

**Exit check:** Booking, rescheduling/status management, form validation, and
communication failure handling work in staging.

**Local implementation note:** Booking, cancellation, basic admin status
management, contact inbox tracking, newsletter actions, and mail-failure
logging are implemented and covered by local tests. Production mail delivery,
SMS, and staging validation are still required.

### Migration 8 — Reviews, blog, and site content

- Port customer and product reviews, image uploads, moderation/approval, and
  testimonial displays.
- Port blog listing, post detail, publication status, and slug behavior.
- Port editable site content and settings, including hero slides, founder
  profile/gallery, staff, branding, and other content actually confirmed in
  Migration 1.
- Keep existing URLs or provide redirects for published content.

**Exit check:** Public content and admin publishing/moderation behavior match
the existing site, including unpublished and empty states.

**Local implementation note:** Customer/product review moderation, basic blog
publishing, and public staff/founder/branch content are implemented locally.
Legacy content import still depends on the Migration 3 importer.

### Migration 9 — Admin operations and AR try-on

- Port admin dashboard and management workflows for products, inventory,
  orders, appointments, customers, reviews, blog, staff, settings, and other
  confirmed admin modules.
- Port product image/gallery and AR asset management, including the current
  2D/3D assets, positioning controls, and file references.
- Port the browser-side camera/face-tracking experience without moving camera
  processing to the server. Test browser permissions, unsupported devices,
  asset loading, capture, and graceful failure.
- Validate uploads by content and size, generate safe filenames, restrict
  storage/execution behavior, and ensure existing asset URLs remain available
  or are redirected.

**Exit check:** Admin permissions and CRUD workflows pass, and AR try-on has
been verified on supported desktop and mobile browsers.

**Local implementation note:** The admin CRUD and role gates are implemented
and tested locally, and the try-on page has been opened and exercised in the
local browser. Camera permission, capture, and supported-device behavior
still need real desktop/mobile staging checks.

### Migration 10 — Parity, security, and performance verification

- Run unit, feature, integration, and browser tests for all critical journeys.
- Compare staging behavior with the baseline from Migration 1, including
  currency/tax calculations, payment statuses, URL behavior, and emails.
- Test accessibility, responsive layouts, query performance, upload handling,
  authorization boundaries, CSRF protection, and error reporting.
- Review dependency/security advisories and remove obsolete credentials,
  temporary scripts, and debug behavior from the deployed application.
- Confirm backup restoration, monitoring, logs, and incident/rollback
  procedures.

**Exit check:** Critical parity checks pass, known gaps are accepted, and
operations are ready for cutover.

### Migration 11 — Production cutover and legacy retirement

**Current authorization and blockers:** The owner has authorized the cutover
and confirmed `www.bealet.com` as the production domain. The existing site is
hosted on Hostinger; the account's plan and Laravel readiness are not yet
known. The owner reports staging sign-off and a verified backup/restore, but
this workspace contains no evidence to independently validate those checks.
The cutover window is not scheduled, and no production resources have been
provisioned. Do not make production changes until the hosting environment is
confirmed suitable and validated, the exact cutover window is scheduled, and
the reported readiness checks are evidenced and revalidated.

1. **Authorize and prepare:** Identify the production host, supported PHP
   version/extensions, database, domain/DNS owner, deployment operator, and
   business approver. Agree on a maintenance window, write-freeze method,
   expected downtime, rollback owner, monitoring contacts, and stabilization
   period. Provision secrets through the host's secret manager; do not send or
   commit credentials.
2. **Prove the release in staging:** Deploy the exact Laravel release to a
   production-like staging environment using a sanitized database copy.
   Verify migrations, payment provider callback signatures/reconciliation,
   real mail delivery, storage permissions/asset URLs, the scheduler, admin
   permissions, customer and guest checkout, appointments, and desktop/mobile
   try-on. Resolve all Migration 10 blockers and obtain a go-live approval.
3. **Back up and reconcile:** Take a production database and upload/assets
   backup immediately before cutover, then restore it to a separate database
   and verify table counts/checksums and representative customer/order data.
   Compare the actual live schema to Laravel migrations and map every
   runtime-created table/asset. Rehearse the final import on a fresh staging
   restore and reconcile users, orders, payment state, inventory, appointments,
   content, IDs, and new writes. The current importer upserts by ID and
   verifies 24 known tables; it does not delete target-only rows, wrap all
   chunks in one transaction, or include uploaded files. Do not use it as an
   unreviewed production sync or assume it alone reconciles cutover deltas.
4. **Freeze writes and deploy:** Put the legacy site into an announced
   maintenance/read-only mode, or run the explicitly rehearsed delta-sync
   procedure. Record the freeze time and reconcile in-flight orders/payments.
   Deploy the tested release, point Laravel only at the approved production
   Laravel database, run reviewed migrations with `php artisan migrate
   --force`, import/synchronize only according to the rehearsed procedure,
   copy/checksum assets, configure secrets and mail/payment callbacks, and
   switch traffic/DNS. Never point Laravel at the legacy production schema as
   a shortcut.
5. **Verify and monitor:** Check health, logs, TLS/session/CSRF behavior,
   public pages/redirects, admin roles, representative browsing, registration,
   checkout/payment confirmation and callback, inventory, order tracking,
   appointments, email, uploads, scheduled reservation cleanup, and support
   channels. Reconcile payment-provider transactions against paid orders and
   verify inventory/order counts. Define numeric alert/rollback thresholds
   before the window and monitor continuously through stabilization.
6. **Rollback or retire:** If an agreed stop threshold is crossed, freeze
   writes on both systems, preserve a Laravel database/assets snapshot, and
   reconcile all post-cutover orders, payments, appointments, and content
   before restoring legacy service. Never blindly switch writes back to the
   stale legacy database or restore a backup over production. Keep legacy
   code/data intact and read-only until the business owner accepts the
   stabilization evidence; retire it only with separate approval and a final
   verified archive.

**Exit check:** The approved production cutover is verified, post-cutover
orders/payments and inventory reconcile, monitoring and rollback are signed
off, and the owner formally closes the rollback window.

**Current disposition:** Not executed. This workspace has no approved
production target or cutover authorization, so no production deployment,
import, DNS change, live payment/email call, or legacy retirement was
performed. Use the checklist above to prepare the actual cutover with the
production operator; provide credentials only through the infrastructure's
approved secret manager.

## Suggested data mapping

| Existing data | Laravel area |
|---|---|
| `users` | Users, authentication, and admin roles |
| `products` | Product catalog and AR metadata |
| `cart` | Guest/customer carts |
| `orders`, `order_items`, `order_prescriptions` | Checkout and order history |
| `appointments` | Appointment booking and management |
| `wishlist` | Customer wishlists |
| `reviews` | Product reviews and moderation |
| `blog_posts` | Blog and publishing |
| `contacts`, `newsletter` | Contact inbox and subscriptions |
| `staff_members`, `founder_gallery` | Public site content |
| Site settings, hero slides, and other runtime-created tables | Confirm in the live schema, then map explicitly |

## Delivery principles

1. Keep the existing website available while Laravel is built and validated in
   staging.
2. Do not perform destructive schema changes or production imports without a
   tested backup and rollback plan.
3. Use the live schema and application behavior as the migration source of
   truth; the checked-in SQL file may not reflect the current production
   schema.
4. Migrate one coherent feature area at a time and verify it before moving to
   the next stage.
5. Treat payment state as server-verified and idempotent.
6. Preserve customer/order history and published URLs wherever feasible.
7. Track unresolved differences as explicit decisions rather than silently
   changing business behavior.
