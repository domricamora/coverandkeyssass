# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-24
- Phase: **05 Booking Engine + 06 Customer Portal COMPLETE (verified)** → next **07 — PayMongo**
- Tests: PASS — 124 tests, 425 assertions, MySQL `hospitality_os_testing`
- Git: local `master` — gh CLI installed but NOT authenticated; if `gh auth login` runs, create private repo + push

## Environment (ready)

- Windows WAMP PHP 8.3.14 (`C:\wamp64\bin\php\php8.3.14`), Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os` (migrated + permissions/roles/modules re-seeded 2026-09-24), test DB `hospitality_os_testing`.
- The default `php` on PATH is 7.4 — run everything via: `cmd.exe /c "cd /d C:\wamp64\www\ck && _ai\run.bat <cmd>"`.
- Frontend build: `node node_modules/vite/bin/vite.js build` (the `&` in the path breaks npm shims).
- Dev URL: `http://localhost/ck/public`.
- No Python on this machine; use PHP / bash for scripting.

## Next actions (Phase 07 — PayMongo)

1. New module `App\Modules\Payments` (`payments` module slug may need adding to ModuleSeeder), `payments` + `payment_events` tables; PayMongo keys via `.env` / `config/services.php`.
2. Hook point: marketplace reservations are created `pending` by `ReservationController` → create a PayMongo checkout / payment intent for `Booking::total`, redirect the guest.
3. Webhook route (CSRF-exempt, signature-verified) → verify payment server-side → `BookingService::transition($booking, 'confirmed')` inside `asTenantOf`. NEVER confirm from the browser redirect.
4. Idempotency: unique PayMongo event id / payment id; duplicate webhook = no-op (no duplicate payment, no double transition — `canTransitionTo` already rejects pending→confirmed twice, but store the event first).
5. Refund: `cancelled|no_show → refunded` transition should call PayMongo refund; failed payment → leave pending + notify.
6. Customer portal: add Payments tab (transaction history) to `customer::partials.nav`; invoice shows paid/unpaid.
7. Tests with `Http::fake()` for PayMongo; `php artisan test` must pass → update docs → commit.

## Last command run

`_ai\run.bat php artisan test` → PASS (124 tests, 425 assertions).

## Last test result

PASS. Bug found & fixed during Phase 05: `checked_out → completed` was deleting the stayed room-nights (release now happens only when leaving an occupying state). Live race (8 PHP processes, 1 room, dev DB) → exactly 1 booking, 7 clean "sold out" rejections; race tenant deleted afterwards.
