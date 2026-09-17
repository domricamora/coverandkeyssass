# AI.md — Agent Operating Guide (Hospitality OS)

Read this first, then `AI_PROGRESS.md`, `ARCHITECTURE.md`, `DATABASE.md`, `SECURITY.md`, `CHANGELOG.md`.

## Golden rules (from master plan)

- Build ONE phase at a time. Inspect → plan → implement → migrate → test → document → commit.
- Never destroy working functionality. Never rewrite the whole project.
- Multi-tenant isolation, RBAC, and payment/booking correctness are non-negotiable.
- Never hard-code secrets. Never commit `.env`. Never store card data. Always verify payments server-side.
- Never mark a feature done until its tests pass.

## How to run commands (WAMP via WSL)

The agent runs in WSL; the app runs on Windows WAMP. Use the helper batch so PATH is correct:

```
cmd.exe /c "cd /d C:\wamp64\www\ck && _ai\run.bat <command>"
```

`_ai\run.bat` puts `php8.3.14` and `mysql9.1.0` on PATH. Examples:
- `_ai\run.bat php artisan migrate`
- `_ai\run.bat php artisan test`
- `_ai\run.bat composer require <pkg>`  (run.bat aliases composer to the phar)

## Dev URLs

- App: `http://localhost/ck/public`
- (optional) add a WAMP vhost `hospitalityos.local` → docroot `C:\wamp64\www\ck\public`.

## Architecture

Modular monolith under `app/Modules/<Module>/...` (Models, Controllers, Services, Repositories, Requests, Policies, Events, Listeners, Jobs, Routes, Views, Tests, Migrations). See `ARCHITECTURE.md`.

## Session-limit / resume protocol

If nearing token/session limits, STOP cleanly and update:
1. `AI_PROGRESS.md` (phase, completed, in-progress, pending, next task).
2. `SESSION_STATE.md` (exact resume instructions + last command run + last test result).
3. Commit with a clear message.
Then the next session reads those files and continues — no rework.
