# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-27
- Phase: **38 COMPLETE; now: platform admin → React, then production deploy** (see "HAND-OFF session 2" below). Remaining after that: the INCOMPLETE items in `docs/FINAL_AUDIT.md`
- Tests: PASS — 333 tests / 2422 assertions (full suite, 2026-09-26, after guest booking flows), MySQL `hospitality_os_testing`
- Git: `master` → origin <https://github.com/domricamora/coverandkeyssass.git> — **not pushed yet**: the auto-mode classifier blocks `git push` from the agent; the owner runs `git push -u origin master:main` (or adds a Bash permission rule)

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated through Phase 33; local Super Admin admin@coverandkeys.test), test DB `hospitality_os_testing`.
- The default `php` on PATH is 7.4 — run everything via: `cmd.exe //c "_ai\run.bat php artisan <cmd>"` from the repo root.
- Frontend build (local, app under /ck/public): `MSYS_NO_PATHCONV=1 ASSET_URL=/ck/public node node_modules/vite/bin/vite.js build`. Without ASSET_URL, lazy chunks preload from /build/ and 404 (harmless but noisy). Production at the domain root builds without it. (The `&` in the path breaks npm shims.)
- Dev URL: `http://localhost/ck/public`. No Python on this machine; use PHP / bash (prefer the Write tool for files containing quotes — bash heredocs have broken on apostrophes).

## Open items

- **PayMongo sandbox run**: verified only against `Http::fake()`. Put real `sk_test_…` + webhook `whsk_…` in `.env`, register the webhook (curl in `docs/modules/payments.md`), and pay one booking and one billing invoice in test mode before going live.
- Laravel Boost (requested by CLAUDE.md) is not installed — deliberately skipped so far.
- SMS: Semaphore / Twilio drivers exist (set SMS_DRIVER + keys).
- **Push sender**: `push_messages` is an outbox only; add an FCM / APNs worker.
- **Before running `billing:run` on dev / prod data**: it expires every module whose trial ended and that has no subscription. Existing demo tenants have old trials — grant them (Super Admin, no trial) or subscribe them first.

## Next actions

## HAND-OFF (2026-09-27, session 2) — START HERE

**Owner's order this session:** finish the remaining phases, then deploy the production build to the live server (FTP now; SSH only if the owner provides access — ask them how). Checkpoint often: update this file + the resume block in `CLAUDE.md`, commit, `git push origin master:main`.

**Already done (do not redo):** every guest account tab is React (incl. notification settings + profile, 421e777); cPanel deploy path hardened (119f601). Invoices, folio print, POS receipt and public `/pages/{slug}` stay **Blade on purpose** (print / public documents).

**Platform admin (`/admin/*`) → React: DONE (2026-09-27).** All 26 screens are Inertia pages in `resources/js/Pages/Admin/*` (support reuses `Messages/Index` with title props); every admin Blade view deleted; full suite 344 passing. `App\Support\Currency` added (symbol helper, used by commissions/payouts). Owner's local Super Admin: nick.weconnect@gmail.com (password given by the owner in chat; not stored in the repo).

**Owner queue (in order), added 2026-09-27:**
1-3. DONE: logo fix + brand plate, motion (page enter, view transitions, section reveal), optimistic preloading (Inertia prefetch + Speculation Rules) and preloaders (top bar, button spinner). See CHANGELOG.
4. DONE: currency symbol (platform default + per-business override, symbol everywhere; `CurrencyTest`). Full suite 346 passing.
5. Then deploy (below).

(Old plan, done:)

1. `DashboardNav::for()`: if the route name starts with `admin.`, return a new `DashboardNav::admin($route)` (groups: Overview · People & businesses [Users, Businesses, Modules] · Marketplace [Properties, Restaurants, Reported content, Reviews, Categories & locations] · Activity [Bookings, Orders, Payments & refunds, Support] · Money [Subscriptions=admin.billing.index, Pricing, Commissions, Payouts] · Site [CMS pages, Settings, Reports, Logs]), all `spa: true`. The shell + Blade sidebar both read it.
2. Convert each controller's `view(...)` to `Inertia::render('Admin/<Name>', props)`, pages in `resources/js/Pages/Admin/`, using `react/kit.jsx` (Page, Panel, Table, Td, Main, Pager, Stats, Status, Filter, Search, Action, Form, Input, Select, TextArea, Row). Screens (24): Dashboard; Users; Tenants index/create/show/modules; Modules index/create/edit; Listings (+placement form, `admin.listings.placement`); Bookings; Orders; Payments (refund w/ reason); Pricing (plans keyed by id: `{price, is_active}`); Settings (`Setting::KEYS`); Pages index/form; Reports (GET CSV download links — plain form, not Inertia); Logs; Taxonomy; Moderation; Billing admin (invoices mark-paid/void, subscriptions, coupons); Reviews admin; Commissions; Payouts; Support index/show (reuse the two-pane pattern from `Pages/Messages`).
   Controllers: `app/Http/Controllers/Admin/*`, `app/Modules/PlatformAdmin/Controllers/*`, `Billing/Controllers/AdminBillingController`, `Reviews/Controllers/AdminReviewController`, `Wallet/Controllers/Admin{Commission,Payout}Controller`, `Messaging/Controllers/SupportController` (index/show).
3. Tests use `assertSee` on these pages (BillingTest, MarketplaceAdminTest, MessagingTest, ModuleEngineHttpTest, PlatformAdminTest, ReviewsTest, WalletTest): keep asserted strings in props **pre-formatted** (commissions `'2,500.00'`, payout account number, `'Booking '.$ref`, `'Approve'` for pending listings). Logs test asserts `assertDontSee('booking.confirmed')` — only send filtered rows.
4. Delete the Blade views once converted (`resources/views/admin/**`, `PlatformAdmin/Views/*` except `pages/show` + `partials/listing-trust`, `billing::admin`, `reviews::admin`, `wallet::admin`, `messaging::admin`); drop view registrations from providers left with zero views.
5. Full suite (`cmd.exe //c "_ai\run.bat php artisan test"`), browser QA at 390/1440, docs (CHANGELOG, modules), commit + push.

**Then: deploy** (memory `deploy-target`; secrets only in `.deploy.env` / `.env.production`): production build *without* ASSET_URL, `composer install --no-dev`, upload over FTPS (exclude `.deploy.env`, `.git`, `node_modules`, `tests`), migrate + seed per `docs/DEPLOYMENT.md` cPanel section, smoke test `https://ck.deskpulse.click`. Ask the owner whether they can enable SSH (cPanel → SSH Access → add key) — makes migrate/artisan far easier than FTP-only.

## HAND-OFF (2026-09-27, session 1) — superseded

**Done since the dashboard conversion:**
- **Guest account on React (`Pages/Account/*`):** Home, Trips, Trip, Orders, Order, Tables, Reviews, Notifications. They use `PublicShell`, which now has site nav, an account menu with sign-out (business links only for members), a mobile menu and page transitions.
- **Menus:** Team and Business settings show only to roles that can use them. Dashboard/Businesses links show only to business members; guests see neither.
- **Photos:** room-type galleries (Booking.com style), dish photos and menu-section banners via `PhotoController` and `PhotoStrip`. Guests see room photos in the stay panel (full-screen `Gallery`) and dishes and banners in the menu. `DemoPhotoSeeder` fills the demo data.
- **Booking-site features:** price-drop alerts (`favorites:price-drops`, daily 09:00), recently viewed stays (localStorage strip on `/`, `/stays`, search), and the map view (Leaflet + OSM, `StayMap` widget, host map-pin fields).
- **Motion:** `.page-in`, `dialog[open]` and `.menu-in` in `react.css` / `app.css`, all respecting reduced motion.
- **Deploy target** saved (memory `deploy-target`; secrets only in the git-ignored `.deploy.env` and `.env.production`). The owner said to upload "later".

**Next (owner asked, in order):**
1. Remaining guest account tabs to React: Wish list (`FavoriteController::index`), Payments (`PaymentController::index`), Rewards (`Loyalty MemberController::index`), Messages (`GuestMessageController` index/show/create), Notification settings, Profile, the invoice and folio pages. Pattern: `AccountController::nav('<route>')` + `PublicShell` + `AccountTabs`.
2. Classy motion and responsive checks on each converted page (390/768/1440 with `flow.mjs`).
3. Platform admin area to React, then the FINAL_AUDIT items (sandbox keys from the owner, deploy when told).

## HAND-OFF (2026-09-26, checkout without registration + PayPal) — superseded

**Done:** checkout without registration (plan `docs/superpowers/plans/2026-09-26-frictionless-checkout.md`), PayPal Orders v2 beside PayMongo, the pay-now / pay-at-property choice, React staff & rota (`Staff/*`, `MyWork/Index`). Full suite: 341 passing.

**Needs the owner:** real sandbox keys. No `PAYMONGO_*` or `PAYPAL_*` values are set in `.env`, so locally only "Pay at the property" shows. Steps are in `docs/modules/payments.md` → "Sandbox run". The dev DB holds one QA guest booking (BKPSGMGO96, user `qa.guest.…@example.test`) created by the browser test.

**Dashboard React conversion: every sidebar screen is now React** (2026-09-27): Accounting (7 screens), Wallet, Billing (+ invoice), Notifications, Messages (two-pane inbox), Team (the Livewire TeamManager was replaced by `TeamController`), Business settings, Switch business (`Businesses/Index`), plus Staff & rota. `DashboardNav::SPA` lists them all. Still Blade: platform admin (`/admin/*`), guest account pages (`/account/*`), auth, tenant create, notification settings. Full suite: 341 passing.

**Next:** OTA ideas (map view, recently viewed, price-drop alerts); guest account pages → React (trips, orders, reservations) to match the new checkout; the platform admin area; the FINAL_AUDIT items (sandbox keys first).

## HAND-OFF (2026-09-26, guest booking flows) — superseded

**Guest booking flows in React: DONE** (plan `docs/superpowers/plans/2026-09-26-guest-booking-flows.md`, tasks 1–9). Stay panel → `/stay/{slug}/review`; menu widget + cart JSON → React `/cart` checkout; table booking widget; `/continue?to=` sign-in return. Widgets mount over the Blade forms (the no-JS fallback) via `resources/js/widgets.jsx`. QA at 390/1440 done (scratchpad `flow.mjs` = `qa.mjs` plus `js:<expr>` steps; run with `MSYS_NO_PATHCONV=1` or Git Bash mangles `/paths`). Gotcha: `@tailwindcss/forms` colours radios and checkboxes with `text-*`, so add `text-brand`. Harmless local noise: modulepreload hints 404 under `/ck/public` (the imports themselves resolve).

**Next, in order:** (a) the remaining items 2–6 of the list below (OverviewTest, OTA ideas, more Blade→React screens, FINAL_AUDIT items, module docs); (b) optional: a sticky "Book" jump button on phones (the booking panels sit at the bottom of the listing pages).

## HAND-OFF (2026-09-26, last commit 435ba2b) — superseded

**React dashboard rebuild: all 4 phases DONE.** (1) Front desk `/dashboard/front-desk` (12f407e); (2) Restaurant floor `/dashboard/restaurant-floor` (8d27b8d; `App\Modules\Pos\Controllers\FloorController`); (3) Housekeeping & ops `/dashboard/housekeeping` (035967f; the Blade board was replaced by `Housekeeping/Index`); (4) Owner overview `/dashboard` (435ba2b; `App\Support\OwnerInsights`, portfolio across all businesses). The React routes are listed in `DashboardNav::SPA`.

**Tests:** every touched file passes in isolation (26 tests: Workforce, TenantIsolation, Housekeeping, RestaurantFloor, FrontDesk). The last full run hit "table already exists" collisions in `hospitality_os_testing`: **another session appeared to be running tests and editing `resources/js/Pages/Overview/Index.jsx` at the same time** (the committed Overview is that version). Re-run the full suite alone first: `cmd.exe //c "_ai\run.bat php artisan test"`. Before this session it was 321 passing, plus new RestaurantFloor (3) and updated Housekeeping / DemoSeed tests.

**Dev database was rebuilt** (migrate:fresh + demo seed) after fixing a calendar-drift bug in `DemoHistorySeeder`. The pre-rebuild backup is `storage/app/backups/ck-20260926-023914.zip`. Logins: Super Admin `admin@coverandkeys.test` / `CoverKeys2026`; owners `owner@aplaya.example.test` (also kalyecoffee, nidocove) / `password`; group owner `group@coverandkeys.example.test` / `password`; staff e.g. `andrea.aquino@aplaya-beach-resort.example.test` / `password`.

**Task list for the next conversation (in order):**
1. Run the full test suite alone and fix anything real (the earlier collisions were not code failures).
2. Owner overview polish: verify the concurrently-edited `Overview/Index.jsx` at 390/768/1440 (`qa.mjs group@coverandkeys.example.test sp 1440 1 /dashboard`); add a small `OverviewTest` (Inertia props: `kpis.occupancy`, `portfolio` count for the group owner, `money=false` for staff without `accounting.view`).
3. Remaining OTA ideas: pay at property / reserve now pay later, map view of stay results, recently viewed stays (localStorage), price-drop alerts on wish-listed stays.
4. Move more Blade screens to React in the same pattern: Bookings list, Guests (CRM), Staff & rota, Accounting reports.
5. INCOMPLETE items in `docs/FINAL_AUDIT.md` (need owner credentials or a server): PayMongo sandbox, production deploy + smoke test, SMTP, off-site backups (BACKUP_OFFSITE_DISK / BACKUP_PASSWORD), push worker (FCM/APNs), and the owner's `git push -u origin master:main` (agent pushes are blocked).
6. Update `docs/modules/*` for the phases 2–4 screens (the changelog is current).

---

**Earlier resume note (superseded by the hand-off above), saved at 4433b87:** owner said "when tasks queue is cleared proceed with the rest of the phases till session limit".

Owner queue (a–d) is DONE:
- a. Guest photo tour: WebP multi-upload with tour areas; `marketplace::partials.mosaic` + `partials.tour` (fullscreen viewer); 8 captioned Unsplash tour photos per demo listing (e33c774). `public/storage` is linked locally.
- b. Full demo platform: `DemoHistorySeeder` (called by DemoOperationsSeeder) gives 20 staff per business with logins and roles, rota, clock-ins and leave; ~90 days of stays and ~60 days of POS tickets; a live "today" (in house, arrivals, departures, 30 days ahead); group owner `group@coverandkeys.example.test` / password owns all 3 businesses. Idempotency flags live in `tenants.settings.demo_seeded`. A full dev seed takes ~3 min.
- c. Marketing redesign: Tailwind public layout + home / features ("For hosts") / pricing / contact, full width; palette v3 Lagoon & Coral applied platform-wide (token override at the end of `resources/css/app.css`). The owner then **cancelled** a sky-blue gradient idea: keep lagoon. Owner rules: thin borders only on the search `.filter-bar` (nothing inside it); one gutter scale 20/40/64px; SEO + AI pass done (FAQPage, ItemList, SoftwareApplication, `/llms.txt`).
- d. OTA features (Booking.com / Airbnb / Agoda research): date search with only-available stays and total stay price (`StayQuoteService`), "only N left", free-cancellation policy (`policies.free_cancellation_days`) with badge and filter, review-score filter, listing-page quote + cancellation deadline, review category scores with a Booking-style summary.

**Next, in order:**
1. React rebuild continues: (2) Restaurant floor: live table plan, fast POS with modifiers, kitchen display, reservations book. (3) Housekeeping & ops: mobile-first room board, maintenance queue, rota. (4) Owner overview: occupancy / ADR / RevPAR / covers / revenue charts across businesses (the group owner has 90 days of data). Pattern: add the route name to `DashboardNav::SPA`, render with `Inertia::render('X/Index')` from a controller, pages in `resources/js/Pages`, shell `resources/js/react/Shell.jsx`, tokens in `resources/css/react.css`.
2. More OTA ideas not built yet: pay at property / reserve now pay later, map view of results, recently viewed stays, price-drop alerts.
3. INCOMPLETE items in `docs/FINAL_AUDIT.md` (need owner credentials or a server): PayMongo sandbox, deploy, SMTP, off-site backups, push worker, owner `git push`.

QA tips: `qa.mjs` paths are relative to `/ck/public`. `scratchpad/chips.mjs` shows how to probe live layout over CDP (port 9335). GateGuard asks for facts before the first edit of each file, so state them and retry.

0. **(Front desk DONE)** Rebuild the dashboard in React + Tailwind via Inertia.js. — owner found the Blade dashboard half-baked (missing real hotel/restaurant workflows, confusing nav, layout issues). Decided: React (not Vue), Laravel stays back end (reuse services, permissions, tenant isolation, tests). Order: (1) Front desk — today view (arrivals/departures/in-house), drag-and-drop room tape chart, one-screen check-in/out with folio + payment; (2) Restaurant floor — live floor plan, fast POS with modifiers, kitchen display, reservations book; (3) Housekeeping & ops — mobile-first room board, maintenance queue, rota; (4) Owner overview — occupancy/ADR/RevPAR/covers/revenue charts. Full-screen shell already live (commit 3db6892). Start: `composer require inertiajs/inertia-laravel`, npm `@inertiajs/react react react-dom @vitejs/plugin-react` (run npm via `node "C:/Program Files/nodejs/node_modules/npm/bin/npm-cli.js"`), add `HandleInertiaRequests` middleware + `resources/views/app-react.blade.php` root, migrate screens one by one behind the existing routes.
   Since the audit: fixed Guests page crash (CRM watermark cached as object, d59e594), richer demo data (983db2a), Semaphore/Twilio SMS drivers (054656b) — SMS no longer INCOMPLETE.

1. Design pass + follow-ups DONE (2026-09-26): cinematic video hero; browser QA of 33 pages at 390/768/1440 (no console errors, no 4xx/5xx, no overflow); responsive module grids; live demo operations (`DemoOperationsSeeder`, covered by `DemoSeedTest`).
2. Phase 31 — API DONE (`docs/API.md`). Phase 32 — Security audit DONE (`docs/SECURITY.md`, accepted risk: CSP script-src still inline/eval). Phase 33 — Performance DONE (`docs/PERFORMANCE.md`; deferred: tenant-aware queued notifications → Phase 35). Phase 34 — Testing DONE (`docs/TESTING.md`). Phase 35 — Production deployment DONE (`docs/DEPLOYMENT.md`, `deploy.sh`, `.env.production.example`, root `.htaccess`, schedule in `routes/console.php`). Phase 36 — Backups DONE (`docs/BACKUPS.md`; set BACKUP_OFFSITE_DISK + BACKUP_PASSWORD before go-live). Phase 37 — Monitoring DONE (`docs/MONITORING.md`). Phase 38 — Final audit DONE (`docs/FINAL_AUDIT.md`). **Now: close the INCOMPLETE items**: (1) PayMongo sandbox run, (2) production deploy + smoke test, (3) off-site backups + BACKUP_PASSWORD, (4) SMS driver, (5) push worker, (6) SMTP, (7) owner pushes git (`git push -u origin master:main`). Keep the demo seeder extended for any new screen (memory: demo-data-live).
3. Browser QA script: `node qa.mjs <email|-> <outdir> <width> <shoot 0|1> <paths… | @sidebar>` (session scratchpad; recreate from CHANGELOG notes if the scratchpad is gone).
4. Owner preferences live in memory: design-direction (square, ink + champagne, Instrument Sans + Geist), demo-data-live, git-autonomy.

## Design / media facts

- Screenshots: `node shoot.mjs <email> <outdir> <width> <paths…>` in the session scratchpad (CDP login + capture). Rebuild CSS with `node node_modules/vite/bin/vite.js build`.
- Demo login: owner@aplaya.example.test / password. Re-seed demo data: `_ai\run.bat php artisan db:seed --class=MarketplaceDemoSeeder` (idempotent).
- Media: `public/img/demo/*.jpg` (Unsplash), `public/media/hero-resort.{mp4,jpg}` (Pexels). Credits in `public/img/demo/CREDITS.md`.
- Git remote: origin = https://github.com/domricamora/coverandkeyssass.git

## Last command run

`_ai\run.bat php artisan test` → PASS (305 tests, 1965 assertions).
