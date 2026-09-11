# Hospitality OS

Multi-tenant hospitality SaaS (marketplace + PMS + restaurant + payments), built per
`Hospitality OS — AI Development Master Plan.md`.

## Quick start (local dev, Windows/WAMP)

```powershell
# PHP 8.3 (the default `php` on PATH is 7.4 — do not use it)
$env:Path = 'C:\wamp64\bin\php\php8.3.14;' + $env:Path

# databases (MySQL root/empty on WAMP)
#   hospitality_os          — dev
#   hospitality_os_testing  — tests

composer install
php artisan key:generate
php artisan migrate --seed

# first platform admin (password prompted, min 12 chars)
php artisan superadmin:create "Platform Admin" "you@example.com"

# frontend (npm shims break on the `&` in this folder name)
node .\node_modules\vite\bin\vite.js build

php artisan serve            # http://localhost:8000
php artisan test             # Pest suite (49 tests)
```

## Documentation

- Status/handoff: `AI_PROGRESS.md`
- Full docs: `docs/` (AI, ARCHITECTURE, DATABASE, API, SECURITY, DEPLOYMENT, MODULES, TESTING, CHANGELOG, modules/)
