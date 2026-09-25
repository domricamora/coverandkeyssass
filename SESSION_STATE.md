# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-26
- Phase: **31 COMPLETE (verified)** → next **32 — Security audit**
- Tests: PASS — 280 tests, 1829 assertions, MySQL `hospitality_os_testing`
- Git: `master` → origin <https://github.com/domricamora/coverandkeyssass.git> — **not pushed yet**: the auto-mode classifier blocks `git push` from the agent; the owner runs `git push -u origin master:main` (or adds a Bash permission rule)

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated through Phase 31; local Super Admin admin@coverandkeys.test), test DB `hospitality_os_testing`.
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

1. Design pass + follow-ups DONE (2026-09-26): cinematic video hero; browser QA of 33 pages at 390/768/1440 (no console errors, no 4xx/5xx, no overflow); responsive module grids; live demo operations (`DemoOperationsSeeder`, covered by `DemoSeedTest`).
2. Phase 31 — API DONE (`docs/API.md` has the contract). **Now: Phase 32 — Security audit** (master plan). Keep the demo seeder extended for any new screen (memory: demo-data-live).
3. Browser QA script: `node qa.mjs <email|-> <outdir> <width> <shoot 0|1> <paths… | @sidebar>` (session scratchpad; recreate from CHANGELOG notes if the scratchpad is gone).
4. Owner preferences live in memory: design-direction (square, ink + champagne, Instrument Sans + Geist), demo-data-live, git-autonomy.

## Design / media facts

- Screenshots: `node shoot.mjs <email> <outdir> <width> <paths…>` in the session scratchpad (CDP login + capture). Rebuild CSS with `node node_modules/vite/bin/vite.js build`.
- Demo login: owner@aplaya.example.test / password. Re-seed demo data: `_ai\run.bat php artisan db:seed --class=MarketplaceDemoSeeder` (idempotent).
- Media: `public/img/demo/*.jpg` (Unsplash), `public/media/hero-resort.{mp4,jpg}` (Pexels). Credits in `public/img/demo/CREDITS.md`.
- Git remote: origin = https://github.com/domricamora/coverandkeyssass.git

## Last command run

`_ai\run.bat php artisan test` → PASS (280 tests, 1829 assertions).
