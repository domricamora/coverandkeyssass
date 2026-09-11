# API.md

Phase 01 ships the web surface only. A versioned REST API (`/api/v1`) arrives with the phase that first requires it; this file records the contract when that happens.

## Web routes (Phase 01)

Guest:
- `GET /` landing · `GET|POST /login` · `GET|POST /register` · `POST /logout`
- password reset + email verification routes (Breeze)

Tenant area (`auth`):
- `GET /tenants` business selector · `GET|POST /tenants/create|store`
- `POST /tenants/{tenant}/switch` — enter business context (membership + active status enforced)

Business context (`auth` + `tenant.context`):
- `GET /dashboard` overview
- `GET /dashboard/team` — Livewire full-page component (owner/manager only, policy-checked)
- `GET|PATCH /dashboard/settings` — rename business (policy: tenants.update)

Super Admin area (`auth` + `super.admin`):
- `GET /admin` platform overview
- `GET /admin/users` · `POST /admin/users/{user}/suspend` · `POST /admin/users/{user}/activate`
- `GET /admin/tenants` · `GET|POST /admin/tenants/create|store` · `GET /admin/tenants/{tenant}`
- `POST /admin/tenants/{tenant}/suspend|activate` · `DELETE /admin/tenants/{tenant}`

CLI: `php artisan superadmin:create {name} {email} {--password=}`.
