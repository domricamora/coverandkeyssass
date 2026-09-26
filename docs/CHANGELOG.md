# CHANGELOG.md

## 2026-09-26 — Photo tours, full demo history, marketing redesign, SEO/AI, OTA features

(verified: 321 tests / 2110 assertions; public pages QA-passed at 390 and 1440 px)

- **Guest photo tour:** Airbnb-style mosaic plus a tour by area, with a fullscreen viewer (arrows, swipe, Esc). Demo listings are seeded with captioned Unsplash photos.
- **Demo platform:** `DemoHistorySeeder` gives each of the 3 businesses a 20-person team (logins and roles), rota, clock-ins and leave. It adds ~90 days of stays and ~60 days of register tickets through the real services, a live "today", and a group-owner login across all businesses.
- **Marketing site:**
  - Rebuilt in Tailwind, full width: home, For hosts, pricing, contact, layout and footer.
  - Lagoon & Coral palette across the platform (public, Blade dashboard, React dashboard).
  - SEO and AI search: keyword-led titles, answer-first copy, FAQPage, ItemList, SoftwareApplication and Organization structured data, and a live `/llms.txt`.
- **OTA features** (Booking.com / Airbnb / Agoda research):
  - Date search showing only available stays, with the total stay price from the booking engine.
  - Honest "only N left".
  - Free-cancellation policy with badge and filter; review-score filter.
  - Listing-page quote with the cancellation deadline and a pre-filled reserve form ("You won't be charged yet").
  - Review category scores with a Booking-style summary.
- **Fixes:** WebP conversion is memory-safe for large phone photos; public mobile layout fixes; one gutter scale on the public site.

## 2026-09-26 — Dashboard rebuild, step 1: React front desk + photo tours (in progress)

- **Front desk (React + Inertia):** `/dashboard/front-desk`. It shows today's arrivals, departures and in-house guests with one-click check-in and check-out; settling a balance comes before check-out. KPIs cover occupancy, dirty rooms and out-of-order rooms. A 14-day room tape chart lets staff drag a stay to another room (`BookingService::moveRoom` moves the remaining nights; the unique index catches conflicts), and a booking drawer holds the folio, quick payment and charges.
- **Stack:** Inertia v3, React 19, @vitejs/plugin-react. `resources/css/react.css` scopes Tailwind preflight to the React screens. Palette v3 "Lagoon & Coral" (light, fresh) is in use on the React dashboard.
- **Navigation:** `App\Support\DashboardNav` is one grouped, permission-filtered sidebar (Hotel / Restaurant / Guests / Team & money / Settings) used by both the Blade and React shells.
- **Fix:** the notifications page no longer hides the business sidebar. It now uses `tenant.context:optional`.
- **Photo tours:**
  - Hosts upload several photos at once, each tagged with a tour area (`media.caption`).
  - Uploads are re-encoded to WebP with GD: at most 2000 px, EXIF rotation respected, metadata stripped (`App\Support\MediaUploads`).
  - 55 curated Unsplash tour photos are in `public/img/demo/tour/`, with credits in CREDITS.md.
- **Tests:** FrontDeskTest (6) plus a WebP upload test.

## 2026-09-26 — Phase 38: Final system audit (v0.38.0)

(verified: 305 tests / 1965 assertions; 34/34 pages pass browser QA; `composer audit` and `npm audit` clean; live backup restore; live API sweep)

- `docs/FINAL_AUDIT.md`: a verdict with evidence for each of the 28 areas in the master plan.
- 21 areas pass outright. Unproven parts are marked **INCOMPLETE**, as the plan requires:
  - PayMongo sandbox run (payments and subscriptions)
  - first production deploy and smoke test
  - off-site backup copy
  - SMS delivery driver
  - push delivery worker
- Also listed before launch: real SMTP configuration, and pushing the local commits to the git remote.
## 2026-09-26 — Phase 37: Monitoring (v0.37.0)

(verified: 305 tests / 1965 assertions)

- Two new daily log channels: `ops` (business events, 30 days) and `security` (auth and attack signals, 90 days).
- `AuditLogger` mirrors every audit entry to `ops` with who, where and what only. That covers bookings, orders, payments, refunds, payouts, subscription and billing changes, and admin actions. Levels come from the action: failures and mismatches are warnings; refunds, cancellations and voids are notices. Old and new values (possible personal data) stay in the database.
- Login monitoring in `security`:
  - `auth.login`
  - `auth.login_failed` (email and whether the account exists, never the password)
  - `auth.lockout` (alert)
  - `api.token_failed`
- PayMongo webhook logging, which didn't exist before: received, duplicate and malformed events go to `ops`; invalid signatures go to `security`.
- Every logged exception carries `tenant_id`, `user_id` and the URL.
- `docs/MONITORING.md`: the streams, the coverage matrix against the master plan, and what to alert on in production.
- 3 new tests (`MonitoringTest`) that read the real log files.
## 2026-09-26 — Phase 36: Backups (v0.36.0)

(verified: 302 tests / 1952 assertions; live `backup:verify` restored 120 tables / 2,725 rows from the dev database)

- `App\Support\BackupManager` with the `backup:run`, `backup:verify` and `backup:restore` commands. One zip per run holds:
  - a consistent `mysqldump`
  - uploaded and private files
  - `.env`, AES-256 encrypted with `BACKUP_PASSWORD`, or left out rather than stored in the clear
  - a manifest with the dump's SHA-256
- Database credentials are passed through a temporary 0600 option file, never the command line.
- Count-based rotation (`BACKUP_KEEP`) and an optional off-site copy to any filesystem disk (`BACKUP_OFFSITE_DISK`).
- **Restore is tested, not assumed.** `backup:verify` restores into a scratch database, checks the checksum and every table, and reports row counts. It is scheduled weekly (Sunday 04:00) next to the nightly `backup:run` (03:00).
- `backup:restore` needs `--force`; `--no-files` restores the database only.
- `docs/BACKUPS.md`: what is backed up, the schedule, rotation, configuration, the step-by-step restore procedure and a new-server recovery.
- 3 new tests (`BackupTest`): a real dump restored row for row, tamper detection, rotation. They commit their probe row through a second connection because `mysqldump` can't see the test transaction.
## 2026-09-26 — Phase 35: Production deployment (v0.35.0)

(verified: 299 tests / 1941 assertions; `route:cache` and `config:cache` succeed; live probe of the dev server)

- **Security fix, found by probing the dev server.** With the repo root as the web root (WAMP, or shared hosting that can't change the document root), `.env`, `.git/config`, logs, `vendor/` and `composer.json` were all served with HTTP 200.
  - A new root `.htaccess` returns 403 for dotfiles, source, config, storage, vendor, tooling and docs, lets `.well-known` through for AutoSSL, and routes everything else into `public/`.
  - Verified: every one of those paths now returns 403.
- **Scheduler.** Nothing was scheduled before, so in production billing, accounting, campaigns and trial warnings would never have run. `routes/console.php` now schedules:
  - `marketing:run` every 15 min
  - `billing:run` at 01:00
  - `accounting:sync` at 02:00
  - `notifications:trials` at 08:00
  - a per-minute `queue:work --stop-when-empty --max-time=55`, for cPanel with no supervisor
  - daily pruning of failed jobs and expired tokens
- **Queued notifications, tenant-aware.** `ChannelNotification` implements `ShouldQueue`:
  - In-app stays synchronous; mail, SMS and push go through the queue.
  - The business is captured at dispatch (from the context or the carried model), and the `RestoreTenantContext` job middleware runs the job inside it and always restores the previous context.
  - Tested end to end with a real `queue:work`, plus a direct middleware test that includes the exception path.
- **Templates and runbook.**
  - `.env.production.example`: debug off, Redis, encrypted sessions, SMTP, PayMongo, token expiry.
  - `deploy.sh`: maintenance mode with a secret bypass, pull, Composer without dev packages, build, migrate, caches, storage link and permissions, queue restart, and always bringing the site back up.
  - `docs/DEPLOYMENT.md` rewritten: cPanel first install, the cron entry and its schedule, the queue worker on cPanel vs supervisor, the webhook URL, the smoke test and hard rules.
- **Dev defaults.** Local `QUEUE_CONNECTION=sync`, so everything is visible instantly on localhost without a worker.
- **Tooling fix.** `_ai\run.bat composer …` works; earlier, `%1` was expanded before `shift` inside the block.
- 2 new tests (`QueuedNotificationsTest`). `NotificationsTest` now uses `notifyNow()` for its unsaved stand-in model.
## 2026-09-26 — Phase 34: Testing (v0.34.0)

(verified: 297 tests / 1931 assertions)

- Coverage audit. `docs/TESTING.md` maps every critical scenario in the master plan (double booking, tenant access, permission bypass, duplicate webhook, failed payment, refund, inventory release, order status, delivery) to the test that proves it. It also includes a per-module matrix.
- Gaps closed:
  - `CrossTenantModulesTest`: another business's loyalty members, rewards and campaigns return 404, even for an owner with full permissions.
  - `Unit/StateMachinesTest`: booking and order transitions, the rule that every room-occupying status can leave occupancy, refund eligibility, and the API money shape. No database needed.
- Cleanup: CRM now uses the existing `Order::customer()` relation, and the duplicate `Order::user()` added in Phase 33 is removed.
- 11 new tests.
## 2026-09-26 — Phase 33: Performance (v0.33.0)

(verified: 286 tests / 1861 assertions; measured with the new `PERF_TRACE` crawl, see `docs/PERFORMANCE.md`)

- Permissions resolve in one query per business per request, memoised on the user. It used to be three queries per `hasPermissionTo()` call, about 20 calls a page. Dashboard pages dropped from 65–79 queries to 12–22.
- Accounting:
  - The ledger loads posted source keys once per sync, so there are no per-row existence queries.
  - Report screens re-sync only live and recent folios. The nightly `accounting:sync` runs `sync(full: true)` as the backstop.
- CRM sync is incremental: a per-business watermark means only changed rows are folded and only touched contacts get their metrics refreshed. A missing watermark means a full pass.
- New reversible indexes: `(tenant_id, updated_at)` on bookings, orders, payments and table_reservations, and `(tenant_id, check_in)` on bookings.
- Dev tooling: `PERF_TRACE=true` logs query counts and lazy-load violations to `storage/logs/perf.log`.
- Fix: `Order` had no `user` relation, so CRM never captured an ordering customer's email.
- Deferred: queued notifications need a tenant-aware job payload (Phase 35).
- 2 new Pest tests (`PerformanceTest`: dashboard query budget, incremental CRM correctness).
## 2026-09-26 — Phase 32: Security audit (v0.32.0)

(verified: 284 tests / 1858 assertions; `composer audit` and `npm audit` clean; browser sweep with CSP live found no violations)

- Full audit across the master plan's checklist, recorded per area in `docs/SECURITY.md`.
- Added the global `SecurityHeaders` middleware:
  - `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `COOP`, and HSTS (production over HTTPS).
  - A CSP pinning third-party origins to Google Fonts, with `object-src 'none'`, `base-uri 'self'`, `form-action 'self'` plus the PayMongo checkout redirect, and `frame-ancestors 'self'`.
- Fixed the password policy: `Password::defaults()` was never configured (min 8). It is now min 10 with letters, mixed case and numbers, plus a Have I Been Pwned breach check in production.
- Fixed the session cookie: it is now `secure` by default in production (was unset).
- Added: HTTPS forced in production, and a critical log entry if `APP_DEBUG` is on in production.
- Auth tests now use policy-compliant passwords.
- 4 new Pest tests (`SecurityBaselineTest`).
## 2026-09-26 — Phase 31: REST API v1 (v0.31.0)

(verified: 280 tests / 1829 assertions)

- New `App\Modules\Api` at `/api/v1`, authenticated with Laravel Sanctum personal access tokens (`laravel/sanctum` ^4.3):
  - Tokens are issued, inspected with `/auth/me` and revoked.
  - Unknown emails get the same error as wrong passwords, so accounts can't be probed. Suspended users are refused.
  - `read` / `write` abilities, with every mutating route requiring `write`.
  - Tokens expire after 30 days. Rate limits: 120 requests a minute overall, 6 a minute for token issue, 20 a minute for placing bookings and orders.
- Public catalogue: property and restaurant search (same filters and service as the website), detail pages, room types, reviews and the full menu with modifier ids.
- Customer `/me`:
  - Read their own bookings, orders, payments, notifications and message threads.
  - Book a stay (same rules as "Request to book"; creates a pending marketplace booking).
  - Place a cash pickup or delivery order (prices computed on the server).
- Business `/business` with the `X-Tenant` header (membership required, 404 otherwise):
  - users, properties, rooms, bookings (+ status changes through the state machine), restaurants, orders (+ status changes), delivery zones, payments, CRM customers, reviews and subscription.
  - Each endpoint checks the same permission as its dashboard screen.
- Responses use explicit whitelists (`Api\Support\Present`): money as a decimal string plus currency, ISO-8601 dates, and no internal columns such as `tenant_id`.
- Demo operations:
  - `DemoOperationsSeeder` seeds bookings in every state (past stays created by moving the clock), settled folios, staff, stock, maintenance tickets, menus and orders, then syncs CRM and accounting.
  - Covered by `DemoSeedTest`.
- Fix: `Employee::nextNumber()` used the global auto-increment id, so one business's employee numbers jumped whenever another business hired. Numbers now continue each business's own `employee_no` sequence.
- Fix: `_ai\run.bat composer …` passed the word "composer" to Composer (`%1` was expanded before `shift` inside the block).
- 5 new Pest tests (API: 4, demo seed: 1).
## 2026-09-26 — Design pass: marketing site, dashboards, demo media (v0.30.1)

(verified: 275 tests / 1779 assertions; screens checked at 390 and 1440 px in headless Chrome)

- Visual system:
  - Palette v2: cool ink with a champagne accent (was mustard gold on warm brown).
  - Square geometry: no rounded corners, except avatars.
  - Type: Instrument Sans for display (replaces Playfair) and Geist for UI, with lining tabular numerals.
- Marketing:
  - Home hero with a royalty-free video: Pexels 4069480, 720p, 20 s, 4 MB, poster image. It loads only on wide screens without reduced motion.
  - Plain-numeral stat band.
  - Module catalogue as two-column rows with a distinct icon per module.
  - The roadmap now shows what has actually shipped (Phases 1–30), shared between the home and features pages.
  - Features page redesigned.
  - Pricing: three plan cards (Foundation, typical hotel stack, live marketplace commission rate), one grouped catalogue table and the FAQ.
  - One label per signup CTA ("Get started"). Em-dashes removed from the copy.
- Demo data:
  - 30 royalty-free Unsplash photos, three per listing, in `public/img/demo` (credits in `CREDITS.md`).
  - Demo businesses have every module enabled with no trial.
  - Each demo property has two bookable room types and rooms, so "Request to book" works.
- Dashboard:
  - Overview with a setup checklist driven by real data and four stats.
  - Sidebar grouped into Front of house / Operations / Guests / Finance. Links are hidden when the user lacks the permission, and so are empty headings.
  - Mobile drawer navigation, both public and dashboard.
  - Collapsible search filters on phones.
  - Tables scroll inside their cards.
  - Controls share one 44 px height.
  - Native selects and date pickers follow the dark/light theme.
- Fixes:
  - Public headings inherited `slate-900` and were invisible on the dark theme.
  - Sidebar icons had no size.
  - Avatars had no background.
  - Container gutters were lost on `.container.section`.
  - The Tailwind content paths missed `app/Modules/**` views.
  - `ModuleService::enableForTenant()` now clears `expires_at`. Re-enabling an expired module used to leave it locked.
  - The 403 page shows the real reason (for example, an inactive module) and links signed-in users back to their dashboard.
  - The module-inactive message had an operator-precedence bug.
## 2026-09-26 — Phase 30: SEO (v0.30.0)

(verified: 275 tests / 1779 assertions)

- The public layout now emits a meta description, canonical URL, Open Graph and Twitter tags (with the listing's cover image) and an optional robots `noindex`. It also renders one JSON-LD `@graph`.
- New `App\Support\Seo` builds the schema.org nodes:
  - Hotel / Resort / B&B / Hostel / LodgingBusiness for stays, with an Offer and Product for the nightly rate.
  - Restaurant with its Menu and priced MenuItems.
  - AggregateRating and Review.
  - BreadcrumbList.
  - FAQPage and Product + Offer on the pricing page.
  - WebSite + SearchAction on the home page.
- Visible breadcrumbs on stays, destinations, listings, restaurants, pricing and CMS pages.
- Pricing page FAQ. The visible FAQ and the FAQPage markup come from the same array.
- `/sitemap.xml` (published listings, destinations, marketing and CMS pages) and a dynamic `/robots.txt`: fully closed outside production, private areas fenced in production. The static `public/robots.txt` is removed.
- Filtered search results are `noindex, follow`, and `/search` canonicalises to `/hotels`.
- Fix: public-page headings inherited `slate-900` from the Tailwind body rule and were near-invisible on the dark theme.
- 6 new Pest tests.
## 2026-09-26 — Phase 29: Marketplace Administration (v0.29.0)

(verified: 269 tests / 1738 assertions)

- Placement: featured (with an optional end date), sponsored (paid, dated, always labelled) and a ranking boost. The recommended order in search and the home page now puts live sponsored listings first, then live featured ones, then a score (rating, review volume, verification, boost).
- Verification of properties, restaurants and hosts, shown to guests as badges.
- Super Admin CRUD for locations, property categories, cuisines and amenities. Entries in use can't be deleted.
- Guests can report a listing. A moderation queue lets admins suspend the listing (with a note), resolve or dismiss.
- 4 new Pest tests.

## 2026-09-26 — Phase 28: Super Admin (v0.28.0)

(verified: 265 tests / 1680 assertions)

- The platform dashboard now covers money (guest payments, commission, subscription revenue), volume (bookings, orders), supply (hosts, listings awaiting approval) and demand (customers), plus subscriptions and payouts. There is a nav bar to every admin screen.
- New `App\Modules\PlatformAdmin`:
  - Property and restaurant control: approve, publish, suspend with a reason, send back.
  - Cross-business bookings, orders and payments.
  - Admin refunds through the booking / order state machine.
  - Module pricing editor.
  - CMS pages: safe Markdown at `/pages/{slug}`, with footer links.
  - Platform settings: support contact, announcement banner, default commission %.
  - CSV reports, with protection against spreadsheet formulas.
  - Audit log viewer.
- The users list gains a hosts / customers filter. `Payment` gains a `user` relation.
- The pricing page reads each module's Monthly plan explicitly (modules now have Yearly plans too).
- 6 new Pest tests.

## 2026-09-26 — Phase 27: SaaS Billing (v0.27.0)

(verified: 259 tests / 1602 assertions)

- New `App\Modules\Billing`. Businesses buy modules one by one, monthly or yearly (yearly = 10 × monthly), and required modules are added automatically. There is one subscription per business, with items per module.
- Invoices are issued at subscribe, on a mid-period add (prorated) and at each renewal. They are idempotent by source key. Trials are charged only from the trial end.
- Coupons: % or ₱ off, for once, n invoices or forever. Redemptions are capped and race-safe.
- `billing:run` (daily):
  - Renews periods and ends cancelled subscriptions.
  - Marks invoices past due at the due date, with an owner notice.
  - Suspends modules after 7 days of grace; paying restores them.
  - Expires trials on modules nobody pays for.
- Payment: PayMongo checkout. The return URL and webhook re-verify the exact total, and the webhook falls back to invoices. A Super Admin can also confirm a bank transfer.
- Usage and limits: live counts of properties, rooms, restaurants and team members against the plan limit or a per-business override. They are enforced when these are created.
- New screens: the business Billing page and invoice page (owner-only `billing.view/manage`), and Super Admin `/admin/billing` (MRR, invoices, subscriptions, coupons).
- Fix: `notifications:trials` found no subscriptions from cron (tenant scope).
- 8 new Pest tests.

## 2026-09-26 — Phase 26: Notifications (v0.26.0)

(verified: 251 tests / 1519 assertions)

- New `App\Modules\Notify`. Every notification now extends `ChannelNotification`, which always sends in-app and adds email, SMS or push depending on the user's per-event preference or the event's default. SMS and push also need a phone number or a registered device. Push is written to a `push_messages` outbox for a future mobile sender.
- The 8 existing notifications (booking, order, reservation, task, ticket, message, review reply, low stock) moved onto the new base. Their payload keys are unchanged, and each now also carries `event` and `link`.
- New notifications:
  - Payment received and payment failed (new `PaymentFailed` event, dispatched from `markFailed`).
  - New online order, sent to staff with `orders.view` (new `OrderPlaced` event).
  - Module trial ending, sent to owners by `php artisan notifications:trials`, once a day.
- Staff notification centre `/dashboard/notifications`, with a sidebar badge and click-through that marks the notification read. The guest notification list also follows the link. New settings page `/account/notification-settings`, and push device registration routes for the mobile app.
- A paid booking now leaves two guest notifications (confirmation + receipt). The PaymentsTest assertion was updated to match.
- 6 new Pest tests.

## 2026-09-26 — Phase 25: Messaging (v0.25.0)

(verified: 245 tests / 1477 assertions)

- New `App\Modules\Messaging`: guest ↔ host and guest ↔ restaurant conversations about a listing or the guest's own booking / order / table, guest ↔ platform support, and private staff conversations. One access service decides every read and write.
- Private attachments behind an access-checked download, per-participant read status with unread counts, database notifications to the other side, and CRM logging of guest conversations. Close and reopen.
- Entry points on listing, trip and order pages, a guest inbox (customer nav "Messages"), a business inbox (sidebar), and a Super Admin support inbox.
- Permissions `messages.view/reply` (owner, manager, front desk).
- 4 new Pest tests.

## 2026-09-26 — Phase 24: Reviews (v0.24.0)

(verified: 241 tests / 1428 assertions)

- New `App\Modules\Reviews`: verified reviews per stay (property + room type), completed food order (restaurant + per-dish stars) and table visit, once each. Overall plus category ratings (cleanliness, location, service, value, food, amenities).
- Aggregates on listing pages: overall, category bars, room-type ratings, favourite dishes, host rating across the business, and the host's reply under each review.
- Host screen: filters, public replies (first reply notifies the guest), report abuse. Super Admin moderation: keep / publish or hide with a note, reported reviews first. Hidden reviews leave the ratings.
- Fix: listing ratings now recalculate when a review changes outside a tenant context (Super Admin moderation). `ReviewObserver` resolves the listing without the tenant scope.
- `reviews` gains the owning business (backfilled), verification links, category ratings and moderation fields. One review per booking replaces one per listing. The customer-portal stay review now goes through `ReviewService`.
- Permissions `reviews.view/reply`. Reviews link in the sidebar, and in the admin dashboard.
- 4 new Pest tests.

## 2026-09-26 — Phase 23: Loyalty (v0.23.0)

(verified: 237 tests / 1381 assertions)

- New `App\Modules\Loyalty` (crm module): points programme (₱100 = 1 point by default, off until enabled), earned when stays check out and orders complete. Event-driven plus sync, idempotent, reversed on refund. Append-only locked ledger.
- Tiers Bronze / Silver / Gold / Platinum on lifetime points. Referral codes pay both guests once, on the new guest's first earning.
- Rewards: points → personal coupon (Phase 22) or store credit.
- Gift cards and store credit in one locked balance mechanism: sold or issued, spent as a POS tender and a folio payment, void with breakage. Posted to accounting as a gift-card liability and a loyalty expense.
- Guest "Rewards" tab (memberships at every business, referral code entry, balances). Host loyalty screens. Permissions `loyalty.view/manage`.
- 6 new Pest tests.

## 2026-09-26 — Phase 22: Marketing (v0.22.0)

(verified: 231 tests / 1336 assertions)

- New `App\Modules\Marketing` (crm module): email and SMS campaigns to opted-in guests (everyone, a segment or a tag). Placeholders, personal single-use coupons, draft / schedule / send. Resumable, never doubled. Signed one-click unsubscribe. Every message is logged in the CRM history.
- Coupons: personal codes that redeem a promotion on bookings and food orders (`Promotion::lookup()` / `redeem()`), spent with a race-safe conditional update. Shared discount codes stay plain promotions.
- Automations via `php artisan marketing:run`: abandoned booking, abandoned cart (signed-in carts saved through Ordering's `CartChanged` event), review request, post-stay offer with coupon, customer reactivation. Each goes out once per guest; marketing follow-ups need consent.
- Pluggable `SmsSender` (log / array drivers; carrier still to add). `config/services.php` gains `sms.driver`.
- Permissions `marketing.view/manage`. Marketing link in the sidebar.
- 7 new Pest tests.

## 2026-09-26 — Phase 21: CRM (v0.21.0)

(verified: 224 tests / 1284 assertions)

- New `App\Modules\Crm` (crm module): guest contacts folded from bookings, food orders and table reservations (matched by account → email → phone, idempotent), with cached metrics (stays, orders, table visits, lifetime spend, first seen, last active).
- Profiles show live booking, order and table history, tags, notes, a VIP flag, marketing consent with timestamp, and communication history (de-duplicated by source key for system senders).
- Seven segments: VIP, Frequent Guest, Inactive, High Spender, New Customer, Restaurant Customer, Hotel Customer. The list has counts, tag and search filters.
- Permissions `crm.view/manage` (owner, manager, front desk). "Guests" link in the sidebar.
- 4 new Pest tests.

## 2026-09-26 — Phase 20: Accounting (v0.20.0)

(verified: 220 tests / 1243 assertions)

- New `App\Modules\Accounting` (finance module): double-entry general ledger with a default chart of accounts. Unbalanced entries are refused, postings are idempotent by `source_key`, and corrections are reversals.
- Automatic postings from folios (room / F&B / other revenue, cash / bank / platform-wallet payments, refunds, voids), completed and refunded food and POS orders (output VAT, delivery fee), commissions, payouts, stock (inventory, COGS, waste, supplies, supplier payables from POS) and maintenance costs. Screens sync on view, and `php artisan accounting:sync` runs for all businesses.
- Expenses with input VAT; customer invoices (draft → issued → partial and full payments, void); supplier payments capped at what is owed.
- Reports: period P&L, VAT (output − input), balances, trial balance with a balanced check, journal by account, payables by supplier, open invoices with aging, commissions and payouts.
- Permissions `accounting.view/manage`. Accounting link in the sidebar.
- 6 new Pest tests.

## 2026-09-26 — Phase 19: POS (v0.19.0)

(verified: 214 tests / 1188 assertions)

- New optional catalogue module `pos` (depends on `restaurant`) and `App\Modules\Pos`.
- Register tickets are orders with `channel = pos`: dine-in at a table or counter, straight to the kitchen (consumes stock), add lines, manual discount with a reason. Same pricing and tax engine as online orders.
- Cashier: split payments (cash with change, card, e-wallet) only inside an open cash session. Charge to an in-house guest's room (posted to the folio as food). Close check, void unpaid ticket, full refund out of the drawer. Printable receipts.
- Kitchen display for all channels with bump (accepted → preparing → ready).
- Cash sessions: opening float, expected cash, counted cash, variance, Z-report (takings by method, refunds, net, discounts, tax).
- Guards: unpaid dine-in tables cannot be completed, and the online host queue cannot close or refund register tickets.
- Permissions `pos.use/discount/refund/manage`. POS link on the restaurant page.
- 5 new Pest tests. The module-catalogue test now expects 10 modules.

## 2026-09-26 — Phase 18: Inventory (v0.18.0)

(verified: 209 tests / 1122 assertions)

- New `App\Modules\Inventory` (inventory module): categories, units with conversion, suppliers, stock locations, items (SKU, weighted-average cost, reorder level), per-location levels and an append-only movement ledger with running balances.
- Moves lock the item row, then the level row. Receive, issue, waste (reason required), stock count (adjustment) and transfer. No negative stock except for sales.
- Low-stock alerts: a notification to managers when an item crosses its reorder level, plus a low-stock filter.
- Purchase orders: draft → ordered → partially received → received at the ordered cost. Over-receipt is blocked. Cancel only before receiving.
- Menu recipes with unit conversion (150 g → 0.15 kg). An accepted food order deducts its ingredients from the restaurant's kitchen location, idempotently. Cancelling an accepted order restores them.
- Screens: stock list, item ledger with movement form, purchase orders, recipes. Permissions `inventory.view/manage`, `purchasing.manage`.
- 6 new Pest tests.

## 2026-09-26 — Phase 17: Staff Management (v0.17.0)

(verified: 203 tests / 1066 assertions)

- New `App\Modules\Workforce`: departments, positions, and employees (numbered, optionally linked to a member account whose tenant role gives their permissions).
- Weekly roster of shifts: 15 minutes to 16 hours, no overlaps, not on approved leave, overnight shifts supported, row-locked per employee.
- Attendance: clock in / out matched to the covering shift, with late minutes and minutes worked. One open clock-in at a time. Managers can clock staff in and out.
- Leave: requests with overlap checks. Approval cancels the shifts inside the range. Reject with a note, or withdraw while pending.
- "My work" self-service: shifts, assigned housekeeping tasks and maintenance tickets, clock button, leave.
- Permissions `staff.view/manage`, `schedules.manage`, `attendance.manage`, `leave.approve`. Sidebar shows Staff or My work.
- 6 new Pest tests.

## 2026-09-25 — Phase 16: Maintenance (v0.16.0)

(verified: 197 tests / 1008 assertions)

- New `App\Modules\Maintenance` provider (workforce): tickets with category, priority, assigned staff (notified), cost, started / resolved / closed times.
- Workflow open → in_progress ⇄ on_hold → resolved → closed, with reopen. Workers claim or progress their own tickets. Managers assign, cost and close. Closed tickets are read-only.
- Notes thread with system notes for every change. Private attachments (images / PDF, 5 MB) on the local disk behind an authorised download route.
- When a room's last active ticket is resolved, the room goes back to housekeeping as dirty. Housekeeping's "Report issue" now opens tickets through `MaintenanceService`.
- Maintenance list and detail screens, sidebar link. Permissions `maintenance.view/work/manage`.
- 4 new Pest tests.

## 2026-09-25 — Phase 15: Housekeeping (v0.15.0)

(verified: 193 tests / 961 assertions)

- New `App\Modules\Housekeeping` (workforce module): `rooms.housekeeping_status` (dirty, cleaning, clean, inspected, maintenance, out_of_order) and `housekeeping_tasks`.
- Check-out makes the rooms dirty and queues a checkout clean. Tasks follow start → complete → inspect. A failed inspection sends the room back to dirty and queues a high-priority re-clean for the same housekeeper.
- Assignment only to members of the business, with a database notification. Housekeepers work their own or unassigned tasks; managers override.
- "Report issue" opens a `maintenance_tickets` row (base of Phase 16) and sets the room to maintenance or out of order. Out-of-order rooms leave `Room::sellable()`, so they cannot be booked.
- Housekeeping board with a room grid, task queue, "My tasks" and open tickets. Permissions `housekeeping.view/work/manage`. The `staff` role becomes the housekeeping role.
- 5 new Pest tests.

## 2026-09-25 — Phase 14: Guest Folio (v0.14.0)

(verified: 188 tests / 918 assertions)

- New `App\Modules\Folio`: `folio_entries`, a per-booking ledger of charges, payments and refunds, with voids and no deletes.
- An idempotent sync posts room nights (weekday and weekend rates; only nights stayed after an early check-out), the promo discount, online PayMongo payments and refunds, and room-service orders charged to the room (Phase 13). Cancelled orders and cancelled-before-stay nights are voided.
- Desk: charges (food, laundry, minibar, activities, transport, other), payments (cash, card, transfer, e-wallet), refunds capped at the net paid, voids of manual lines with a reason. Totals by category and balance due. Printable folio.
- The guest's read-only folio is linked from the trip page, and the host folio from the booking page.
- Permissions `folio.view/manage` (owner, manager, front desk), `folio.void` (owner, manager).
- 7 new Pest tests.

## 2026-09-25 — Phase 13: Hotel Room Service (v0.13.0)

(verified: 181 tests / 851 assertions)

- Orders can be delivered to the room (`fulfillment = room_service`) for signed-in guests with a checked-in stay at a property of the same business. The order is linked to the booking and room.
- "Charge to my room" (`payment_method = room_charge`, `payment_status = charged`) for the folio (Phase 14). Cash and online also work. No payment or commission is created for room charges.
- Room service follows the delivery states without a driver. The ETA is prep time plus a 10-minute walk.
- Restaurant setting `room_service_enabled`. The cart shows a room picker and the room-charge option. Order screens use `fulfillmentLabel()` / `paymentLabel()`.
- Fix found by the new tests: an explicit room id outside a single-room stay no longer falls back to that room.
- 5 new Pest tests.

## 2026-09-25 — Phase 12: Delivery (v0.12.0)

(verified: 176 tests / 820 assertions)

- New `App\Modules\Delivery`: `delivery_zones` (named area or radius around the restaurant; fee, free-over, minimum order, ride ETA, pause) and `delivery_drivers`.
- Checkout: delivery needs an active zone that covers the drop-off point (haversine against the restaurant's coordinates; browser "share my location"). The fee comes from the zone and the minimum order is enforced. Optional scheduled delivery or pickup (prep time up to 7 days ahead).
- Dispatch: drivers can be assigned to accepted delivery orders. Out-for-delivery is refused without a driver, and a driver cannot be cleared while the order is on the road. Assignments are audited.
- ETAs: set on acceptance (scheduled time, or prep plus ride) and on dispatch (ride). Delivered time is recorded. The customer sees ETA, driver and delivered time.
- Host delivery setup screen (zones, prep time, coordinates, drivers); driver dropdown on the order queue and detail. New permission `delivery.manage` (owner, manager).
- 6 new Pest tests. The Phase 11 delivery test moved to zone-based delivery.

## 2026-09-25 — Phase 11: Online Food Ordering (v0.11.0)

(verified: 171 tests / 776 assertions)

- New `App\Modules\Ordering`: `orders` and `order_items` (snapshot names, modifiers, prices and tax settings); `OrderService` (quote, place, 9-state machine, audit, customer notifications); session `CartService` (one restaurant per cart).
- Server-side pricing only: every line is priced through `MenuItem::priceWith()`. Order promo codes use `promotions` (new `applies_to` column; stay and order codes are kept apart). Per-restaurant tax rate, inclusive (PH VAT default) or exclusive.
- Public "Add to order" on the menu (modifier radios or checkboxes, qty, notes), `/cart`, and checkout for pickup or delivery, paid in cash or online (PayMongo).
- Payments and Wallet generalised: `payments.order_id` / `commissions.order_id` sit beside `booking_id`. Online orders earn commission at the restaurant rate, released on completed and reversed on refund. A refund refused by PayMongo vetoes the transition.
- Host order queue per restaurant (by stage, with actions), order detail, and order promo codes. Customer "Orders" tab with pay, cancel-while-pending and payment return.
- `TenantContext::runAs()` replaces the duplicated tenant switch in BookingService and ReservationService.
- Permissions `orders.view/manage` (owner, manager, front desk). Restaurant settings `ordering_enabled`, `tax_rate`, `tax_inclusive`.
- 9 new Pest tests.

## 2026-09-25 — Phase 10: Restaurant Reservations (v0.10.0)

(verified: 162 tests / 680 assertions, plus a live 8-process race for one table → exactly 1 reservation)

- `table_reservations` and `ReservationService`: time slots from opening hours and the sitting length; table allocation (smallest fit) under a `FOR UPDATE` lock on the restaurant's tables. Overlapping pending, confirmed or seated reservations can never share a table.
- State machine: pending → confirmed → seated → completed, plus cancelled and no-show. Date guards on seat and no-show; completing early frees the table. Audited transitions; the customer is notified on confirm or cancel.
- Host desk `/dashboard/restaurants/{slug}/reservations`: day calendar per table, booking list with actions, phone bookings (confirmed immediately, optional table choice).
- Marketplace "Book a table" on `/restaurant/{slug}` (slot picker from `/restaurant/{slug}/slots`). Requests start as pending. Customer "Tables" tab at `/account/reservations` with self-cancel.
- New permissions `reservations.view/manage` (owner, manager, front desk); new setting `restaurants.reservation_duration_minutes`.
- 6 new Pest tests.

## 2026-09-25 — Phase 09: Restaurant Management (v0.9.0)

(verified: 156 tests / 621 assertions)

- New `App\Modules\RestaurantManagement` (gated by `module.active:restaurant`): host profile CRUD, opening hours per day, cuisines, contact details, reservations/delivery flags, photos with cover, audited publish/unpublish.
- Menu builder: `menu_categories` → `menu_items` (price, photo, availability) → `modifier_groups` (min/max rules: required modifiers or optional add-ons) → `modifier_options` (price delta).
- `MenuItem::priceWith(optionIds)` is the server-side price calculation that ordering will use. It enforces group rules, option availability and option ownership.
- Floor plan: `dining_areas` and `restaurant_tables` (label unique per restaurant, seats, active/inactive).
- The public `/restaurant/{slug}` page shows the menu, with "Sold out" items and add-on prices.
- Permissions `menu.view/manage` and `tables.view/manage` (owner and manager manage; front desk views). Restaurants link in the sidebar.
- 10 new Pest tests.

## 2026-09-24 — Phase 08: Host Wallet & Commissions (v0.8.0)

(verified: 146 tests / 560 assertions)

- New `App\Modules\Wallet`: `commission_rates`, `commissions`, `wallets`, `wallet_transactions`, `payouts`; `WalletService` (row-locked, ledgered balance changes).
- Paid payment → commission split at the resolved rate (promotional listing → promotional all → listing → global → default 10%) → host pending balance. Released to available on check-out or no-show; reversed on refund.
- Payouts: host request (min ₱100, at most the available balance); Super Admin marks paid with a transfer reference, or rejects (credited back).
- Super Admin commission rates (global / property / restaurant / promotional) and platform revenue totals; admin dashboard links.
- Payments: `PaymentPaid` / `PaymentRefunded` events; the paid status, earning and booking confirmation now commit in one transaction, so a failure rolls back and the webhook retry completes it. Booking: `BookingTransitioned` event.
- Permissions `wallet.view` (owner, manager), `payouts.request` (owner); Wallet sidebar link. PayMongo test fake moved to `tests/Support/PayMongoFake.php`.
- 10 new Pest tests.

## 2026-09-24 — Phase 07: PayMongo (v0.7.0)

(verified: 136 tests / 485 assertions)

- New `App\Modules\Payments`: `payments` + `payment_events` tables, `PayMongoGateway` (checkout sessions, refunds, signature verification) and `PaymentService`.
- Checkout → webhook / return URL → **server-side session lookup** (status, amount, currency) → booking confirmed. Never confirmed from a redirect or a webhook body alone.
- Idempotent: event ledger with unique event ids, row-locked `markPaid`, unique provider ids, reuse of an open checkout.
- Failed payments recorded with retry; refunds go through PayMongo when a booking is marked refunded (a provider refusal vetoes the transition); a payment that lands after cancellation is recorded and flagged.
- Signed webhook outside the web group (no CSRF/session), with a 5-minute replay window.
- Customer portal Payments tab, payment panels on guest and host booking pages. Booking emits a new `BookingTransitioning` event.
- 12 new Pest tests (PayMongo faked). Real-sandbox run still to do.

## 2026-09-24 — Phase 06: Customer Portal (v0.6.0)

(verified: 124 tests / 425 assertions)

- New `App\Modules\Customer` module: `/account` dashboard (upcoming/past trips, wish-list/review/notification counts), trips list and detail, printable invoice, self-cancel before the check-in date, one verified review per property after check-out, and database notifications with mark-read.
- Cross-tenant customer reads go through `Booking::forCustomer()` only; tenant relations and writes run in `BookingService::asTenantOf()`.
- "My trips" link in both layout menus. Orders, payments, wallet, loyalty, coupons and messages are deferred to the phases that build them.
- 7 new Pest tests.

## 2026-09-24 — Phase 05: Booking Engine (v0.5.0)

- New `App\Modules\Booking` module: `bookings`, `booking_rooms`, `room_nights`, `promotions`.
- Double booking is prevented by a transaction plus `FOR UPDATE` lock on the room type's rooms, backed by a unique `(room_id, night)` index. Verified with a live race of 8 concurrent processes for one room: exactly 1 booking.
- 9-state machine (pending, held, confirmed, checked_in, checked_out, cancelled, no_show, refunded, completed) with date guards, audited transitions, inventory release on cancel/no-show/early check-out, and auto-cancel of expired holds.
- Pricing per night (rate periods, then weekend price, then base), minimum stay, promo codes (percent/fixed, window, min nights, max uses).
- Host desk: list with filters, manual / hold / walk-in / multi-room / group reservations with live availability, booking detail with actions, 14-day room calendar, promotions screen.
- Marketplace: "Request to book" widget on the property page (only when the business runs the booking module) → pending booking.
- `AvailabilityService::freeRoomIds()` now subtracts booked nights; `availableRoomCount()` uses it.
- Permissions `bookings.view|create|update`, `promotions.manage` (front desk gets bookings, not promotions). Host dashboard now shows real property / upcoming reservation counts.
- 19 new Pest tests.

## 2026-09-17 — Phase 04: Property Management (v0.4.0)

Host-side property management (verified: 98 tests / 320 assertions):

- New `App\Modules\PropertyManagement` module managing the Marketplace `Property` rows: profile CRUD (description, policies JSON, amenities sync, location, check-in/out, pricing), publish/unpublish with audit trail, draft-first lifecycle.
- Media: additive `kind` column on `media` (image/video) — photo + video URLs, single cover with promotion, gallery/video helpers on `HasMedia`.
- Inventory: `room_types` → `rooms` (physical inventory with per-property unique room numbers, active/maintenance/inactive states), `rate_periods` (date-range price overrides with overlap rejection), `availability_blocks` (room type or single room, inclusive ranges).
- `AvailabilityService`: sellable-inventory resolution (`blockedRoomIds`, `availableRoomCount`) — the foundation the Phase 05 booking engine consumes.
- Property staff: per-property assignments restricted to active business members, unique per property + user, audited.
- Module gating: generic `module.active:<slug>` middleware; host area requires the `property` module (trial-aware via ModuleService).
- Permissions: `rooms.*`, `rates.*`, `availability.*`, `properties.staff.manage` added to `PermissionRegistry`; system role map extended (owner full, manager no deletes, front_desk view-only); `db:seed` now refreshes existing tenants' roles.
- Tenant-scoped UI: sidebar "Properties" link (permission-gated), five management screens on the bnb design system.
- Correctness: route model binding runs before `tenant.context` (middleware priority), so all `{property}`/nested params resolve manually through tenant-scoped relations — cross-tenant rows are plain 404s.
- Tests: 24 new Pest tests covering gating, isolation, authorization per role, CRUD, media, inventory, rates, availability and staff.

## 2026-09-17 — Phase 03: Marketplace (v0.3.0)

Public, cross-tenant marketplace (verified: 70 tests / 181 assertions):

- Public surface: `/stays` landing, `/hotels` + `/search` with keyword/destination/price/guests/type filters and sorting, `/hotels/{location}` destination pages, `/property/{slug}` and `/restaurants` + `/restaurant/{slug}` detail pages.
- Tenant-owned listings with `draft → pending → published → suspended` lifecycle; published rows only on the public side (`publicQuery()` — the single cross-tenant read path).
- Polymorphic media (covers/galleries) with cover fallback; amenities for stays, cuisines for dining.
- Guest reviews (polymorphic, 1–5) with rating aggregates maintained by observers; wish list (favorites) with auth + personal scope and type whitelist.
- `PropertyPolicy` registered for host-side abilities ahead of Phase 04; additive morph map (`property`, `restaurant`).
- Marketing site: features / pricing / contact pages wired into the public layout.
- Reference seeders (`MarketplaceReferenceSeeder`) + demo data seeder; module docs completed (`docs/modules/marketplace.md`).

## 2026-09-11 — Phase 02: Module engine (v0.2.0)

(verified: 65 tests / 158 assertions)

- `modules`, `module_features`, `module_plans`, `tenant_modules` tables; `ModuleService` with dependency auto-enable, trials and guarded disable.
- Admin CRUD for modules and tenant-module assignments (enable/disable per tenant, permission-gated); tenant-facing module enable/disable page with timezone-aware trial countdown.
- `ModuleSeeder`: canonical module catalogue (core, property, booking, workforce, restaurant, inventory, finance, crm, analytics) with monthly plans + feature bullets.
- Branding refresh to **Cover & Keys** (bnb design system, Playfair Display) across marketing/auth/app shells; hospitality design system port.

## 2026-09-11 — Phase 01: Foundation (v0.1.0)

Implemented and verified (49 passing tests, 124 assertions):

- Laravel 13 project on PHP 8.3 + MySQL (InnoDB enforced per-connection; WAMP MyISAM default documented and fixed in dev `my.ini`).
- Auth (Breeze Blade): register, login, logout, password reset, email verification; suspended users blocked; last-login stamping.
- Multi-tenancy: tenants/tenant_users, TenantContext + SetTenantContext middleware, BelongsToTenant global scope (deny-by-default without context), TenantPolicy.
- RBAC: permissions catalogue (PermissionRegistry), per-tenant system roles (owner/manager/front_desk/staff), platform super_admin role, User permission resolution, granular policy checks.
- Team management: Livewire full-page TeamManager (add/change role/remove with owner uniqueness + audits).
- Super Admin area: platform dashboard, user suspend/activate (session revocation, self-protection), tenant create/suspend/activate/delete; `superadmin:create` command (validated, audited).
- Audit logging: registration, login/logout, tenant lifecycle, team changes, platform actions.
- UI: hospitality design system (Fraunces + Work Sans, evergreen/brass palette), responsive app/guest shells, business selector, dashboard, team, settings, admin screens, branded error pages, dark mode.
- Tests: Pest suite incl. tenant isolation + authorization coverage; permission-cache flush between tests.
- Docs: AI/ARCHITECTURE/DATABASE/API/SECURITY/DEPLOYMENT/MODULES/TESTING + this changelog + AI_PROGRESS.md; module docs stubbed (docs/modules).

Known limitations: Redis not available locally (database queue/cache drivers used); PayMongo integration pending (Phase 07); module engine pending (Phase 02).
