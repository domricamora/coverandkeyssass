# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-26
- Phase: **21 COMPLETE (verified)** → next **22 — Marketing**
- Tests: PASS — 224 tests, 1284 assertions, MySQL `hospitality_os_testing`
- Git: local `master` — gh CLI installed but NOT authenticated; if `gh auth login` runs, create private repo + push

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated through Phase 21; modules/permissions/roles re-seeded), test DB `hospitality_os_testing`.
- The default `php` on PATH is 7.4 — run everything via: `cmd.exe /c "cd /d C:\wamp64\www\ck && _ai\run.bat <cmd>"`.
- Frontend build: `node node_modules/vite/bin/vite.js build` (the `&` in the path breaks npm shims).
- Dev URL: `http://localhost/ck/public`. No Python on this machine; use PHP / bash.

## Open items

- **PayMongo sandbox run**: the integration is verified only against `Http::fake()`. Put real `sk_test_…` + webhook `whsk_…` in `.env`, register the webhook (curl in `docs/modules/payments.md`), and pay one booking in test mode before going live.
- Laravel Boost (requested by CLAUDE.md) is not installed — deliberately skipped so far.

## Next actions (Phase 22 — Marketing)

1. Scope: email and SMS campaigns, promotions, coupons, discount codes, abandoned booking, abandoned cart, review requests, post-stay campaigns, customer reactivation. `marketing` catalogue module? (check ModuleSeeder; crm covers it today).
2. Reuse: CRM segments + consent (only contacts with marketing_consent), `CrmService::log(... sourceKey)` for the communication history, Promotions (stays + orders) for coupons / discount codes, Laravel Mail + a pluggable SMS driver (log driver locally).
3. Automations (abandoned booking = pending/held not paid, abandoned cart = session cart — needs persisted carts for signed-in users, review request after check-out, reactivation for Inactive segment) as a scheduled command.
4. Tests → `php artisan test` green → docs → commit. Also schedule `accounting:sync` daily.

## Last command run

`_ai\run.bat php artisan test` → PASS (224 tests, 1284 assertions).
