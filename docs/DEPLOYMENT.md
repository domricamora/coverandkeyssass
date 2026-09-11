# DEPLOYMENT.md

Target: conventional cPanel / Z.com VPS (Apache, PHP-FPM, MySQL, cron). No Docker required.

## Server requirements

- PHP 8.3+ with extensions: pdo_mysql, mbstring, intl, gd, zip, curl, openssl, bcmath, redis (for Redis).
- MySQL 8+ (InnoDB default).
- Composer 2, Node.js 22 (build-time only).

## Deploy steps (cPanel)

1. Upload repo (or git pull) outside `public_html`; point the document root at `/public`.
2. Copy `.env.example` → `.env`; fill DB, Redis, SMTP, `PAYMONGO_*`, APP_URL. Set `APP_ENV=production`, `APP_DEBUG=false`.
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan key:generate`
5. `php artisan migrate --force` then `php artisan db:seed --force` (permissions + platform role only).
6. Provision the first Super Admin: `php artisan superadmin:create …`.
7. `php artisan storage:link` · `npm ci && npm run build` (run locally and upload `public/build` if the server has no Node).
8. Cache config/routes/views: `php artisan config:cache route:cache view:cache`.

## Scheduled jobs & queues (cPanel cron)

```cron
* * * * * php /home/USER/project/artisan schedule:run
```

Queue worker: run `php artisan queue:work` under a supervisor-equivalent (cPanel: use cron + `queue:restart`, or a daemon entry) once Phase 02+ introduces queued jobs.

## Hard rules

- Public root points to `/public`; never expose `.env`, `storage`, `vendor`, `.git`.
- The app force-sets `default_storage_engine=InnoDB` per connection, so hosts with odd server defaults still get foreign keys.
