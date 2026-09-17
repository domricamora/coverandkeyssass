# AI.md — Agent Operating Instructions

Cover & Keys is built by AI agents, one phase at a time, per the master plan
(`Cover & Keys — AI Development Master Plan.md` at the repository root).

## Before doing any work, read

```text
AI.md
AI_PROGRESS.md
ARCHITECTURE.md
DATABASE.md
API.md
SECURITY.md
DEPLOYMENT.md
CHANGELOG.md
```

## Environment

| Item       | Value                                    |
|------------|------------------------------------------|
| PHP (CLI)  | `C:\wamp64\bin\php\php8.3.14\php.exe` — prepend to PATH; the default `php` on PATH is 7.4 and must NOT be used |
| MySQL      | WAMP MySQL 9.1 (`wampmysqld64`), host `127.0.0.1`, user `root`, empty password (local only) |
| Databases  | `hospitality_os` (dev), `hospitality_os_testing` (tests) |
| Node       | v22 — `npm run build` works, but the `&` in the project path breaks `.bin` shims under cmd. Use `node .\node_modules\vite\bin\vite.js build` instead |
| Tests      | `php artisan test` (Pest, against `hospitality_os_testing` on real MySQL) |

## Rules (from the master plan, §2/§21 — binding)

1. Never build more than the current phase.
2. Never claim a feature is complete unless tests prove it.
3. Never destroy working functionality; extend it.
4. Tenant-owned tables always go through the `BelongsToTenant` scope.
5. Never store secrets in the repo; `.env` is git-ignored.
6. Always run `php artisan test` before updating `AI_PROGRESS.md`.
7. Update `AI_PROGRESS.md` and `CHANGELOG.md`, then commit.

## Current state

Phase 03 (Marketplace) is implemented and passing. Next: Phase 04 (Property Management).
See `AI_PROGRESS.md` for the authoritative status.
