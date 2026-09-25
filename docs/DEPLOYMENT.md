# DEPLOYMENT.md

The target is conventional cPanel hosting or a Z.com / other VPS (Apache or Nginx, PHP-FPM, MySQL, cron). No Docker is needed.

## Server requirements

- **PHP 8.3+** with extensions: pdo_mysql, mbstring, intl, gd, zip, curl, openssl, bcmath, fileinfo, and redis if you use Redis.
- **MySQL 8+.** The app forces InnoDB on every connection, so a MyISAM server default is harmless.
- **Composer 2.** Node.js 22 is needed only to build assets. If the server has no Node, build locally and upload `public/build`.
- **SSL:** cPanel AutoSSL or Let's Encrypt. The app forces HTTPS URLs in production and sends HSTS over HTTPS.
- **Redis** (optional, recommended): cache, sessions and queue. Without it, the database drivers work too.

## First install (cPanel)

1. **Code.** `git clone` into a folder **outside** `public_html`, for example `~/coverandkeys`.
2. **Document root.**
   - Preferred: in *Domains*, set the domain's document root to `~/coverandkeys/public`.
   - If the host can't change it: the repo's root `.htaccess` routes every request into `public/` and returns 403 for `.env`, `.git`, `storage`, `vendor`, source files and docs. `.well-known` stays open for AutoSSL.
3. **Environment.**
   - `cp .env.production.example .env`, then fill in the database, mail, PayMongo and `APP_URL`.
   - Choose Redis or the database drivers (see the comments in the template).
4. **Install.**

   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force          # permissions, roles, modules, reference data (no demo data)
   php artisan superadmin:create "Your Name" you@domain.com
   php artisan storage:link
   ```

5. **Assets.** Run `npm ci && npm run build`, or upload a locally built `public/build`.
6. **Caches.** Run `php artisan config:cache route:cache view:cache event:cache` (all verified to work, since no routes use closures).
7. **Permissions.** `storage/` and `bootstrap/cache/` must be writable by the PHP user: `chmod -R ug+rwX storage bootstrap/cache`.
8. **Cron.** Add one entry in cPanel's *Cron Jobs*. Use the full path to PHP 8.3 if `php` is a different version:

   ```cron
   * * * * * cd /home/USER/coverandkeys && php artisan schedule:run >> /dev/null 2>&1
   ```

   That single entry runs everything in `routes/console.php`:

   | Job | When | What |
   |---|---|---|
   | `marketing:run` | every 15 min | Campaign sends and automations |
   | `billing:run` | 01:00 | Renewals, past due, trial expiry, restores |
   | `accounting:sync` | 02:00 | Full ledger backstop |
   | `notifications:trials` | 08:00 | Trial-ending warnings |
   | `queue:work --stop-when-empty --max-time=55` | every minute | The queue worker on hosts without a supervisor |
   | `queue:prune-failed`, `sanctum:prune-expired` | daily | Housekeeping |

9. **PayMongo webhook.** Register `https://your-domain.com/webhooks/paymongo` for `checkout_session.payment.paid`, `payment.paid` and `payment.failed` (the events the handler processes; refunds are confirmed synchronously through the API). Put its secret in `PAYMONGO_WEBHOOK_SECRET`. The curl command is in `docs/modules/payments.md`.
10. **Smoke test.**
    - `/up` returns 200.
    - `/robots.txt` lists the sitemap (production only).
    - Sign in as the Super Admin.
    - Make one test-mode booking payment and one billing invoice before switching PayMongo to live keys.

## Queue worker

Notifications go through the queue.

- The in-app bell is delivered immediately.
- Email, SMS and push wait for the worker.
- Each job runs inside the business it was dispatched for (`RestoreTenantContext`).

Setups:

- **cPanel:** the scheduled `queue:work --stop-when-empty` above, so no daemon is needed.
- **VPS with supervisor:** remove that schedule line and run a daemon:

  ```ini
  [program:coverandkeys-worker]
  command=php /var/www/coverandkeys/artisan queue:work --sleep=3 --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  user=www-data
  numprocs=1
  stopwaitsecs=3600
  ```

## Every update

Run `bash deploy.sh` from the app root. It will:

1. Put the site in maintenance mode, printing a secret bypass URL.
2. Run `git pull`.
3. Install Composer dependencies without dev packages.
4. Build assets.
5. Migrate.
6. Rebuild the caches.
7. Create the storage link and fix permissions.
8. Run `queue:restart`.
9. Bring the site back up, even if a step failed.

Set `PHP=/opt/cpanel/ea-php83/root/usr/bin/php` (or wherever your PHP lives) if `php` points at an older version.

## Hard rules

- The public root is `/public`. Never expose `.env`, `storage`, `vendor` or `.git`. The root `.htaccess` is a safety net, not the plan.
- `APP_DEBUG=false` in production. The app logs a critical error if it's ever on.
- Never run `db:seed --class=MarketplaceDemoSeeder` in production. It refuses to, because it uses a known demo password.
- Before the first `billing:run` on real data: businesses on expired trials with no subscription get their modules suspended. Grant or subscribe them first.
- The app forces `default_storage_engine=InnoDB` on every connection, so hosts with odd server defaults still get foreign keys.
