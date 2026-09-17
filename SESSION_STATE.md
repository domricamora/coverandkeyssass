# SESSION_STATE.md — Resume Point

> Read this + `AI_PROGRESS.md` to resume without rework.

## Snapshot

- Date: 2026-09-14
- Phase: **01 — Foundation**
- Status: Scaffolding Laravel 13 into `C:\wamp64\www\ck_build`, will merge into project root `C:\wamp64\www\ck`.

## Environment (ready)

- Windows WAMP PHP 8.3.14, Composer 2.10.3, MySQL 9.1.0 (root / no password).
- Dev DB `hospitality_os`, test DB `hospitality_os_testing`.
- Run commands via: `cmd.exe /c "cd /d C:\wamp64\www\ck && _ai\run.bat <cmd>"`.
- Dev URL: `http://localhost/ck/public`.

## Next actions (in order)

1. Confirm scaffold finished (`_ai\scaffold.log` ends with `SCAFFOLD_EXIT=0`).
2. Merge `ck_build/*` (incl. dotfiles) into `ck/`, preserving `.git`, master plan md, and `*.md` docs.
3. Configure `.env`: APP_NAME, DB_DATABASE=hospitality_os, DB_USERNAME=root, empty pass; `phpunit.xml` test DB = hospitality_os_testing.
4. `run.bat php artisan key:generate`, then `run.bat php artisan migrate`.
5. Verify `http://localhost/ck/public` loads default Laravel page.
6. Begin Phase 01 build: install Breeze (or Fortify) auth, Tailwind; add tenants, roles, permissions, RBAC, audit logs, super admin, base dashboard. Migrations + models + policies + tests.
7. Run `run.bat php artisan test` — must pass before Phase 02.
8. Commit.

## Last command run

Laravel scaffold via `_ai\scaffold.bat` (background).

## Last test result

None yet.
