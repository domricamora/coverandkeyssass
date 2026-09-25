# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-26
- Phase: **27 COMPLETE (verified)** → next **28 — Super Admin**
- Tests: PASS — 259 tests, 1602 assertions, MySQL `hospitality_os_testing`
- Git: local `master` — gh CLI installed but NOT authenticated; if `gh auth login` runs, create private repo + push

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated through Phase 27; modules / permissions / roles re-seeded), test DB `hospitality_os_testing`.
- The default `php` on PATH is 7.4 — run everything via: `cmd.exe //c "_ai\run.bat php artisan <cmd>"` from the repo root.
- Frontend build: `node node_modules/vite/bin/vite.js build` (the `&` in the path breaks npm shims).
- Dev URL: `http://localhost/ck/public`. No Python on this machine; use PHP / bash (prefer the Write tool for files containing quotes — bash heredocs have broken on apostrophes).

## Open items

- **PayMongo sandbox run**: the integration is verified only against `Http::fake()`. Put real `sk_test_…` + webhook `whsk_…` in `.env`, register the webhook (curl in `docs/modules/payments.md`), and pay one booking and one billing invoice in test mode before going live.
- Laravel Boost (requested by CLAUDE.md) is not installed — deliberately skipped so far.
- **SMS carrier**: `SmsSender` only logs; add a Semaphore / Twilio driver before real SMS.
- **Push sender**: `push_messages` is an outbox only; add an FCM / APNs worker.
- **Scheduler**: add `marketing:run` (every 15 min), `accounting:sync`, `notifications:trials` and `billing:run` (daily) to the production schedule / cron.
- **Before running `billing:run` on dev / prod data**: it expires every module whose trial ended and that has no subscription. Existing demo tenants have old trials — grant them (Super Admin, no trial) or subscribe them first.

## Next actions (Phase 28 — Super Admin)

1. Master plan PHASE 28. Dashboard: revenue, bookings, orders, hosts, customers, properties, restaurants, subscriptions, commissions, payouts. Controls: users, hosts, customers, properties, restaurants, bookings, orders, reviews, payments, refunds, payouts, modules, pricing, subscriptions, CMS, settings, reports, logs.
2. Existing admin pieces: `/admin` dashboard, users (suspend / activate), tenants (+ modules), modules CRUD, commissions, payouts, reviews moderation, support inbox, billing. Build the missing ones (bookings / orders / payments + refunds browse, properties / restaurants control, pricing edit on module plans, audit log viewer, platform settings, reports, CMS pages); don't duplicate.
3. Tests → `php artisan test` green → docs → commit.

## Last command run

`_ai\run.bat php artisan test` → PASS (259 tests, 1602 assertions).
