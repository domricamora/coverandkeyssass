# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-26
- Phase: **30 COMPLETE (verified)** → next **31 — API**
- Tests: PASS — 275 tests, 1779 assertions, MySQL `hospitality_os_testing`
- Git: `master` → origin https://github.com/domricamora/coverandkeyssass.git

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated through Phase 30; local Super Admin admin@coverandkeys.test), test DB `hospitality_os_testing`.
- The default `php` on PATH is 7.4 — run everything via: `cmd.exe //c "_ai\run.bat php artisan <cmd>"` from the repo root.
- Frontend build: `node node_modules/vite/bin/vite.js build` (the `&` in the path breaks npm shims).
- Dev URL: `http://localhost/ck/public`. No Python on this machine; use PHP / bash (prefer the Write tool for files containing quotes — bash heredocs have broken on apostrophes).

## Open items

- **PayMongo sandbox run**: verified only against `Http::fake()`. Put real `sk_test_…` + webhook `whsk_…` in `.env`, register the webhook (curl in `docs/modules/payments.md`), and pay one booking and one billing invoice in test mode before going live.
- Laravel Boost (requested by CLAUDE.md) is not installed — deliberately skipped so far.
- **SMS carrier**: `SmsSender` only logs; add a Semaphore / Twilio driver before real SMS.
- **Push sender**: `push_messages` is an outbox only; add an FCM / APNs worker.
- **Scheduler**: add `marketing:run` (every 15 min), `accounting:sync`, `notifications:trials` and `billing:run` (daily) to the production schedule / cron.
- **Before running `billing:run` on dev / prod data**: it expires every module whose trial ended and that has no subscription. Existing demo tenants have old trials — grant them (Super Admin, no trial) or subscribe them first.

## Next actions

1. Design pass (owner request 2026-09-26) is committed. Follow-ups still open:
   - QA the remaining dashboard screens at 390/768/1024/1440 with `ecc:browser-qa`/Chrome DevTools (the Playwright MCP failed to connect this session).
   - Seed demo operational data (bookings, orders, housekeeping tasks, guests) so every dashboard screen shows real rows.
   - Hero section polish (owner: "hero looks ugly, video good").
2. Then Phase 31 — API (master plan).
3. Owner preferences live in memory: design-direction (square, ink + champagne, Instrument Sans + Geist), demo-data-live, git-autonomy.

## Design / media facts

- Screenshots: `node shoot.mjs <email> <outdir> <width> <paths…>` in the session scratchpad (CDP login + capture). Rebuild CSS with `node node_modules/vite/bin/vite.js build`.
- Demo login: owner@aplaya.example.test / password. Re-seed demo data: `_ai\run.bat php artisan db:seed --class=MarketplaceDemoSeeder` (idempotent).
- Media: `public/img/demo/*.jpg` (Unsplash), `public/media/hero-resort.{mp4,jpg}` (Pexels). Credits in `public/img/demo/CREDITS.md`.
- Git remote: origin = https://github.com/domricamora/coverandkeyssass.git

## Last command run

`_ai\run.bat php artisan test` → PASS (275 tests, 1779 assertions).
