# DATABASE.md — Hospitality OS

## Connections

- Dev: MySQL `hospitality_os` @ 127.0.0.1:3306 (root / no password).
- Test: `hospitality_os_testing` (used by PHPUnit; wiped per run).

## Conventions

- PK: `BIGINT UNSIGNED` auto-increment `id`. Use UUID/ULID for public-facing/shareable ids where useful.
- Timestamps: `created_at`, `updated_at`. Soft deletes (`deleted_at`) where appropriate.
- Tenant-owned tables carry `tenant_id` (FK → `tenants.id`, indexed).
- Foreign keys enforced; index `tenant_id`, `status`, `slug`, `email`, date ranges.
- No unindexed scans on large tables. Paginate.

## Phase 01 tables

- `tenants` (id, name, slug, domain, status, timestamps, soft deletes)
- `users` (Laravel default + `type` [customer|host|admin|super_admin])
- `tenant_user` (pivot: tenant_id, user_id, role context) — host/staff membership
- `roles` (id, tenant_id nullable, name, guard)
- `permissions` (id, name, group)
- `permission_role` (pivot)
- `role_user` (pivot, optional tenant_id scope)
- `audit_logs` (id, tenant_id nullable, user_id nullable, action, subject_type, subject_id, properties json, ip, created_at)

## Migration safety

- No destructive migrations without explicit warning in CHANGELOG.
- Every reservation/order mutation runs inside a transaction with row locks (see later phases).
