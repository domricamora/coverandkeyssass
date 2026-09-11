# SECURITY.md

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
