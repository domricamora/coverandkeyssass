# SECURITY.md

## Phase 32 — Security audit (2026-09-26)

Scope is the master plan's checklist. Each verdict is based on a code scan, a live check against the dev server, or a test. Tests run: 284 / 1858.

| Area | Verdict | Evidence / change |
|---|---|---|
| Authentication | Pass | Breeze session auth. `LoginRequest` throttles to 5 attempts per email + IP. The session is regenerated on login and invalidated on logout. Suspended accounts are refused (`SecurityBaselineTest`). |
| Authorization / RBAC | Pass | A heuristic scan flagged every controller with no in-method check. Each one was either public by design (marketplace, SEO), behind `super.admin` (admin area), self-scoped (profile), or checks through the base controller's `authorizeTo()` helper (restaurant management). The API `/business` routes check the same permission as the matching dashboard screen. |
| Tenant isolation | Pass | `BelongsToTenant` denies by default. Web routes use `SetTenantContext`; API routes use `ResolveApiTenant` (`X-Tenant` + membership, 404 otherwise). Tested in TenantIsolationTest and ApiTest. |
| SQL injection | Pass | The only raw-SQL interpolation is internal table names (`Promotable`). Every user value is bound. |
| XSS | Pass | Every Blade `{!! !!}` wraps `e()` or holds static markup. JSON-LD is encoded with `JSON_HEX_TAG`, and a script-breakout attempt is tested (`SeoTest`). A CSP is now sent (see below). |
| CSRF | Pass | Laravel default on the whole web group. The only exempt route is the PayMongo webhook, which authenticates by signature. The API uses Bearer tokens only, with no cookie auth. |
| File uploads | Pass | Every upload validates type (`jpg,jpeg,png,webp,pdf`) and size (5 MB). Attachments live on the private local disk and are served through authorised downloads. |
| Rate limiting | Pass | Login is limited to 5/min, API token issue to 6/min, the API overall to 120/min per user, bookings and orders to 20/min, and web reservations to 20/min. |
| API security | Pass | Sanctum tokens hashed at rest, `read`/`write` abilities, 30-day expiry, revocation, uniform failed-credential errors, explicit JSON whitelists (no `tenant_id` or secrets). |
| Session security | **Fixed** | The cookie is `http_only` and `same_site=lax`, and is now `secure` by default in production (was unset). |
| Password security | **Fixed** | `Password::defaults()` was never configured (min 8). It is now min 10 with letters, mixed case and numbers, plus a Have I Been Pwned breach check in production. Passwords are hashed with bcrypt (12 rounds). |
| Webhook validation | Pass | HMAC-SHA256 over timestamp + payload, `hash_equals`, a 5-minute replay window, and an idempotent event ledger. |
| Payment security | Pass | No card data stored. Payments are confirmed only by a server-side PayMongo lookup, with row-locked `markPaid`. Refunds go through the state machines. |
| Audit logs | Pass | Admin, payment, refund, payout, staff, booking and maintenance actions are all written to `audit_logs`. |
| Secrets | Pass | A pattern scan (`sk_live/test`, `whsk_`, AWS keys, literal passwords) found nothing. `.env` is git-ignored. |
| Error handling | Pass | The API always renders JSON errors. There are custom 403, 404, 419, 500 and 503 pages; the 403 page shows only abort messages we wrote. A critical log entry is written if `APP_DEBUG` is on in production. |
| Security headers | **Added** | `SecurityHeaders` middleware (global) sets `nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, `COOP`, HSTS (production over HTTPS) and a CSP. The CSP allows `default-src 'self'`, Google Fonts only, `object-src 'none'`, `base-uri 'self'`, `form-action 'self' https://*.paymongo.com` (the checkout redirect) and `frame-ancestors 'self'`. It is not sent while the Vite dev server runs. A browser sweep found no CSP violations. |
| HTTPS | **Added** | `URL::forceScheme('https')` in production. |
| Dependencies | Pass | `composer audit`: no advisories. `npm audit --omit=dev`: 0 vulnerabilities. |

Accepted risk: `script-src` still allows `'unsafe-inline' 'unsafe-eval'`. Alpine evaluates expressions, and about 44 views use inline `confirm()`/`print()` handlers. To tighten it, move those handlers to Alpine directives, then switch `script-src` to a Vite nonce.

Production checklist (also in DEPLOYMENT.md):

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set
- HTTPS terminated in front of the app
- `SESSION_SECURE_COOKIE` left at its default
- real SMTP
- PayMongo live keys and webhook secret
- `php artisan config:cache`

## Payments (Phase 07)

- PayMongo webhook authenticated by HMAC-SHA256 signature (constant-time compare, 5-minute timestamp window); no session/CSRF on that route only.
- Bookings are confirmed only after a server-side PayMongo lookup confirms status, amount and currency. Redirects and webhook bodies are never trusted.
- Idempotent processing (unique event ids, row lock, unique provider ids). Secret keys only from `.env`.

## Implemented (Phase 01)

- **Authentication** — Laravel session auth (Breeze): registration, login, logout, password reset, email verification. Login throttling (5/min per email+IP) and hashed passwords (bcrypt).
- **Suspended accounts** — cannot authenticate; existing sessions are revoked on suspension (admin action deletes their sessions).
- **Tenant isolation** — `SetTenantContext` middleware validates membership + tenant status on every request; `BelongsToTenant` global scope denies all tenant-owned rows without context; session forging of another tenant id bounces to business selection (tested).
- **RBAC** — granular permissions via roles per tenant; platform `super_admin` role with `Gate::before` override limited to the `EnsureSuperAdmin`-guarded `/admin` area (guarded again inside controllers/policies).
- **CSRF** — Laravel default verification everywhere; custom error page 419.
- **Validation** — FormRequest/controller validation on all writes; Livewire component rules.
- **Audit trail** — registration, login, logout, tenant create/update, team changes, and all platform admin actions recorded with actor, subject, IP and user agent (`audit_logs`).
- **Secrets** — nothing hard-coded; `.env` git-ignored; `.env.example` carries placeholders (PayMongo keys included for later phases).
- **Input** — Eloquent/Query Builder only (no raw SQL with user input); Blade escaping by default.

## Checked

- `composer audit` — no advisories.
- `npm audit --omit=dev` — 0 vulnerabilities.

## Known limitations (to address in later phases)

- Rate limiting on tenant/team endpoints relies on framework defaults; dedicated policies arrive with module phases.
- Password reset emails use the `log` mailer locally; configure SMTP before production (see DEPLOYMENT.md).
- Webhook validation (PayMongo) is specified for the Payments phase and not yet present.

## Never (per master plan §21)

No disabled CSRF/auth, no hard-coded credentials, no card data storage, no tenant isolation bypasses, no `.env` in git.
