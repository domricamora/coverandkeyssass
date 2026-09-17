# SECURITY.md — Hospitality OS

## Non-negotiables

- Secrets only in `.env` (never committed). Never hard-code DB/API/PayMongo/SMTP creds.
- Never store raw card data — PayMongo holds credentials; store only gateway references.
- Verify payments server-side (amount, currency, status, reference, association). Never trust browser redirects.
- Webhooks: verify signature; idempotent processing (no duplicate booking/order/payment).
- CSRF on all state-changing web routes. Auth tokens for API (Sanctum). Rate-limit auth + API.

## Tenant isolation

- Global `TenantScope` + `BelongsToTenant` on tenant-owned models.
- Policies deny cross-tenant access; Super Admin bypass is explicit + audited.
- Tests must assert Tenant A cannot read/write Tenant B data.

## AppSec checklist (revisited each phase)

- [ ] AuthN/AuthZ enforced (no disabled guards)
- [ ] Input validated via FormRequests
- [ ] Output escaped (Blade `{{ }}`), no raw unescaped user data
- [ ] SQL via Eloquent/bindings (no string-concatenated queries)
- [ ] File uploads validated (mime, size, storage outside webroot)
- [ ] Mass-assignment guarded (`$fillable`)
- [ ] Sensitive routes behind permission middleware
- [ ] Audit log for admin/payment/refund/payout actions
- [ ] `composer audit` clean

## Never

Disable auth/CSRF/authorization to "make it work". Commit `.env`. Expose `.env`, `storage`, `vendor`, `.git`.
