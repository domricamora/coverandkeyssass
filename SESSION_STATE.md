# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-25
- Phase: **10 COMPLETE (verified)** → next **11 — Online Food Ordering**
- Tests: PASS — 162 tests, 680 assertions, MySQL `hospitality_os_testing`
- Git: local `master` — gh CLI installed but NOT authenticated; if `gh auth login` runs, create private repo + push

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated through Phase 10; permissions/roles re-seeded), test DB `hospitality_os_testing`.
- The default `php` on PATH is 7.4 — run everything via: `cmd.exe /c "cd /d C:\wamp64\www\ck && _ai\run.bat <cmd>"`.
- Frontend build: `node node_modules/vite/bin/vite.js build` (the `&` in the path breaks npm shims).
- Dev URL: `http://localhost/ck/public`. No Python on this machine; use PHP / bash.

## Open items

- **PayMongo sandbox run**: the integration is verified only against `Http::fake()`. Put real `sk_test_…` + webhook `whsk_…` in `.env`, register the webhook (curl in `docs/modules/payments.md`), and pay one booking in test mode before going live.
- Laravel Boost (requested by CLAUDE.md) is not installed — deliberately skipped so far.

## Next actions (Phase 11 — Online Food Ordering)

1. Cart → checkout → order + order items (snapshot item/option names + prices; price lines with `MenuItem::priceWith()` only), modifiers, discounts (reuse `Promotion`?), taxes, payment (PayMongo via Payments module), order status.
2. States: pending, accepted, preparing, ready, out_for_delivery, delivered, completed, cancelled, refunded.
3. Commission: Wallet already supports restaurant listing rates — hook restaurant order payments into WalletService.
4. Tests → `php artisan test` green → docs → commit.

## Last command run

`_ai\run.bat php artisan test` → PASS (162 tests, 680 assertions).
