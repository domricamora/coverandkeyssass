# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-24
- Phase: **05–08 COMPLETE (verified)** → next **09 — Restaurant Management**
- Tests: PASS — 146 tests, 560 assertions, MySQL `hospitality_os_testing`
- Git: local `master` — gh CLI installed but NOT authenticated; if `gh auth login` runs, create private repo + push

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated through Phase 08; permissions/roles re-seeded), test DB `hospitality_os_testing`.
- The default `php` on PATH is 7.4 — run everything via: `cmd.exe /c "cd /d C:\wamp64\www\ck && _ai\run.bat <cmd>"`.
- Frontend build: `node node_modules/vite/bin/vite.js build` (the `&` in the path breaks npm shims).
- Dev URL: `http://localhost/ck/public`. No Python on this machine; use PHP / bash.

## Open items

- **PayMongo sandbox run**: the integration is verified only against `Http::fake()`. Put real `sk_test_…` + webhook `whsk_…` in `.env`, register the webhook (curl in `docs/modules/payments.md`), and pay one booking in test mode before going live.
- Laravel Boost (requested by CLAUDE.md) is not installed — deliberately skipped so far.

## Next actions (Phase 09 — Restaurant Management)

1. New module `App\Modules\RestaurantManagement` extending the Marketplace `Restaurant` (like PropertyManagement extends `Property`); gate with `module.active:restaurant`; resolve params manually through tenant-scoped relations (see PropertyManagementController).
2. Scope (master plan): restaurant profile, opening hours, menu, categories, menu items, photos, pricing, modifiers, add-ons (e.g. Burger ₱250 + Cheese ₱30 / Bacon ₱50 / Egg ₱25), availability, tables, dining areas.
3. Permissions `restaurants.*` already exist; add `menu.*`, `tables.*` to PermissionRegistry + role map.
4. Commission rates already support `restaurant` listings (Wallet) — restaurant payments arrive with ordering (Phase 11).
5. Tests → `php artisan test` green → docs → commit.

## Last command run

`_ai\run.bat php artisan test` → PASS (146 tests, 560 assertions).
