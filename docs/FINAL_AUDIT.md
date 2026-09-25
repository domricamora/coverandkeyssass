# FINAL_AUDIT.md

## Phase 38 — Final system audit (2026-09-26)

Rule from the master plan: anything not proven to work stays **INCOMPLETE**. A verdict of PASS below means it is proven by automated tests **and** by a live check against the dev server with the demo data. Where only part of an area is proven, the unproven part is listed as INCOMPLETE.

### Evidence gathered for this audit (all fresh, same day)

- **Test suite:** `php artisan test`, 305 tests / 1965 assertions, all passing (MySQL `hospitality_os_testing`).
- **Browser QA:** headless Chrome crawl of 34 pages (public site, and every dashboard sidebar screen as the demo owner). 34/34 PASS: no JS errors, no 4xx/5xx, no horizontal overflow at 1440 px. Earlier the same day: 390 and 768 px, all PASS.
- **Dependencies:** `composer audit` found no advisories; `npm audit --omit=dev` found 0 vulnerabilities.
- **Backups:** a live `backup:run`, then `backup:verify` restored 120 tables into a scratch database.
- **API:** a live token as the demo owner got 200 from all 10 `/business` endpoints and from the public catalogue. The token was revoked afterwards.
- **Deployment:** `route:cache` and `config:cache` succeed, `schedule:list` shows all 9 jobs, and a probe of sensitive paths returns 403 for `.env`, `.git`, `vendor`, `storage` and `composer.json`.

### Verdicts

| # | Area | Verdict | Evidence |
|---|---|---|---|
| 1 | Authentication | PASS | Breeze + TenantFoundationTest, SecurityBaselineTest (password policy, suspended accounts), throttled login, ApiTest (Sanctum tokens) |
| 2 | Marketplace | PASS | MarketplaceHttpTest, MarketplaceAdminTest, SeoTest; live crawl of `/stays`, `/hotels`, destination, property and restaurant pages |
| 3 | Properties | PASS | PropertyManagementTest (24); live `/dashboard/properties` |
| 4 | Rooms | PASS | PropertyManagementTest (types, rooms, maintenance status); API `/business/rooms` |
| 5 | Availability | PASS | BookingEngineTest ("never sells the same room twice", DB unique backstop, availability blocks, expired holds); 8-process race verified in Phase 05 |
| 6 | Booking | PASS | BookingEngineTest (19), CustomerPortalTest, ApiTest (marketplace booking), Unit/StateMachinesTest; live bookings in every state |
| 7 | Payments | PASS in code and tests; **INCOMPLETE: real gateway** | PaymentsTest (12): signature, replay, idempotency, amount mismatch, failed payment, refunds, all against `Http::fake()`. **Not yet run against the PayMongo sandbox.** Needs `sk_test_…` + webhook secret; pay one booking and one billing invoice in test mode. |
| 8 | Restaurants | PASS | RestaurantManagementTest; live `/dashboard/restaurants`, public restaurant pages |
| 9 | Menu | PASS | RestaurantManagementTest (modifiers, pricing rules); live menu on the public page and API `/restaurants/{slug}/menu` |
| 10 | Orders | PASS | OrderingTest (8), PosTest, Unit/StateMachinesTest; demo orders in every state |
| 11 | Delivery | PASS | DeliveryTest (zones, radius, driver required, ETAs, scheduled orders) |
| 12 | Reservations | PASS | RestaurantReservationsTest (smallest-fit tables, race-safe allocation, state machine) |
| 13 | CRM | PASS | CrmTest, PerformanceTest (incremental sync), CrossTenantModulesTest; live Guests screen |
| 14 | Staff | PASS | WorkforceTest (roster rules, attendance, leave, per-business employee numbers) |
| 15 | Inventory | PASS | InventoryTest (moves, POs, recipes, consumption on orders) |
| 16 | Accounting | PASS | AccountingTest, FolioTest; live P&L, VAT and balances from demo data |
| 17 | POS | PASS | PosTest (split payments, cash sessions, Z-report) |
| 18 | Marketing | PASS | MarketingTest (consent-gated campaigns, coupons, automations), CrossTenantModulesTest |
| 19 | Reviews | PASS | ReviewsTest (verified reviews, replies, moderation) |
| 20 | Messaging | PASS (in-app); **INCOMPLETE: SMS and push delivery** | MessagingTest; in-app and email notifications work and are queued with tenant context (QueuedNotificationsTest). **`SmsSender` only logs** (no Semaphore or Twilio driver), and **push is an outbox only** (no FCM or APNs worker). |
| 21 | Subscriptions | PASS in code and tests; **INCOMPLETE: real gateway** | BillingTest (8): invoices, coupons, trials, past due, suspension, restores. Paying an invoice through PayMongo shares item 7's pending sandbox run. |
| 22 | Modules | PASS | ModuleEngineTest, ModuleEngineHttpTest, EnsureModuleActive (fixed so re-enabling an expired module works) |
| 23 | Super Admin | PASS | SuperAdminTest, PlatformAdminTest, MarketplaceAdminTest |
| 24 | SEO | PASS | SeoTest: meta, OG, canonical, JSON-LD (lodging, restaurant, offers, ratings, FAQ, breadcrumbs), sitemap, robots |
| 25 | Security | PASS, with one accepted risk | docs/SECURITY.md per-area audit; SecurityBaselineTest; headers and CSP live; sensitive paths return 403. Accepted risk: CSP `script-src` still allows inline and eval (Alpine plus about 44 inline handlers). |
| 26 | Performance | PASS | docs/PERFORMANCE.md: dashboard pages 65–79 down to 12–22 queries; PerformanceTest query budget; reversible indexes |
| 27 | Backups | PASS; **INCOMPLETE: off-site copy** | BackupTest (real dump restored, tamper detection, rotation); live `backup:verify`; weekly scheduled restore test. **`BACKUP_OFFSITE_DISK` and `BACKUP_PASSWORD` are not configured yet.** A server-only backup does not survive losing the server. |
| 28 | Deployment | Artifacts PASS; **INCOMPLETE: not yet deployed** | `deploy.sh`, `.env.production.example`, docs/DEPLOYMENT.md, schedule, cPanel worker, root `.htaccess` (blocking verified). **No production server has been provisioned or smoke-tested.** Routing through the root `.htaccess` fallback on a host whose document root can't point at `/public` is unverified. |

### INCOMPLETE items (launch blockers first)

1. **PayMongo sandbox run** (items 7 and 21). Put test keys and the webhook secret in `.env`, register the webhook (docs/DEPLOYMENT.md step 9), then pay one booking, one order and one billing invoice, and refund one. Switch to live keys only after that.
2. **First production deploy and smoke test** (item 28). Follow docs/DEPLOYMENT.md, then the smoke test in step 10.
3. **Off-site backups** (item 27). Configure a disk and `BACKUP_OFFSITE_DISK`, set `BACKUP_PASSWORD`, and store the password off the server.
4. **SMS delivery** (item 20). Add a Semaphore or Twilio driver behind `SmsSender`. Until then SMS notifications are logged, not sent.
5. **Push delivery** (item 20). Add an FCM/APNs worker for the `push_messages` outbox. Until then push is queued, not delivered.
6. **Real SMTP** before launch: password resets and guest emails need it. It's configuration only; see `.env.production.example`.
7. **Git remote.** The commits are local. The owner runs `git push -u origin master:main`, since the agent's push was blocked by the auto-mode safety classifier.

### Accepted risks (documented, not blockers)

- CSP `script-src 'unsafe-inline' 'unsafe-eval'`. Tightening path: move about 44 inline handlers to Alpine directives, then use a Vite nonce.
- Accounting and CRM screens sync on view. They are now scoped and incremental, and the nightly full `accounting:sync` is the backstop.
