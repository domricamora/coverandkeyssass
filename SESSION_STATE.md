# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-26
- Phase: **25 COMPLETE (verified)** → next **26 — Notifications**
- Tests: PASS — 245 tests, 1477 assertions, MySQL `hospitality_os_testing`
- Git: local `master` — gh CLI installed but NOT authenticated; if `gh auth login` runs, create private repo + push

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated through Phase 25; modules/permissions/roles re-seeded), test DB `hospitality_os_testing`.
- The default `php` on PATH is 7.4 — run everything via: `cmd.exe /c "cd /d C:\wamp64\www\ck && _ai\run.bat <cmd>"`.
- Frontend build: `node node_modules/vite/bin/vite.js build` (the `&` in the path breaks npm shims).
- Dev URL: `http://localhost/ck/public`. No Python on this machine; use PHP / bash.

## Open items

- **PayMongo sandbox run**: the integration is verified only against `Http::fake()`. Put real `sk_test_…` + webhook `whsk_…` in `.env`, register the webhook (curl in `docs/modules/payments.md`), and pay one booking in test mode before going live.
- Laravel Boost (requested by CLAUDE.md) is not installed — deliberately skipped so far.
- **SMS carrier**: `SmsSender` only logs; add a Semaphore / Twilio driver before real SMS.
- **Scheduler**: add `marketing:run` (every 15 min) and `accounting:sync` (daily) to the production schedule / cron.

## Next actions (Phase 26 — Notifications)

1. Read master plan PHASE 26. Many modules already emit database notifications (booking status, reservations, orders, tasks, tickets, messages, reviews, low stock). Likely: a unified notification centre for staff (the customer portal already lists notifications), per-user channel preferences (database / email / SMS), email delivery for key events, mark-all-read, unread badge in the nav.
2. Tests → `php artisan test` green → docs → commit.

## Last command run

`_ai\run.bat php artisan test` → PASS (245 tests, 1477 assertions).
