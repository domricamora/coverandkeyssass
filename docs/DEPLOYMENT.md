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
   | `backup:run` | 03:00 | Database, uploads and encrypted `.env` archive with rotation (docs/BACKUPS.md) |
   | `backup:verify` | Sunday 04:00 | Real restore into a scratch database to prove the latest backup |
   | `queue:prune-failed`, `sanctum:prune-expired` | daily | Housekeeping |

9. **PayMongo webhook.** Register `https://your-domain.com/webhooks/paymongo` for `checkout_session.payment.paid`, `payment.paid` and `payment.failed` (the events the handler processes; refunds are confirmed synchronously through the API). Put its secret in `PAYMONGO_WEBHOOK_SECRET`. The curl command is in `docs/modules/payments.md`.
10. **Smoke test.**
    - `/up` returns 200.
    - `/robots.txt` lists the sitemap (production only).
    - Sign in as the Super Admin.
    - Make one test-mode booking payment and one billing invoice before switching PayMongo to live keys.

## Shared cPanel hosting (this build: `ck.deskpulse.click`)

The temporary domain `ck.deskpulse.click` is a cPanel account. There are three ways in; pick one and stay with it.

### Server setup (once, in cPanel)

1. **PHP 8.3** — *MultiPHP Manager*: set the domain to `ea-php83`. In *Select PHP Version* enable `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `curl`, `openssl`, `bcmath`, `fileinfo`.
2. **Database** — *MySQL Databases*: create the database and a user with all privileges on it, then put the `cpanelaccount_`-prefixed names in `.env`.
3. **Document root** — *Domains*. Either set it to `<app>/public` (the clean way), or leave it and let the repo's root `.htaccess` route everything into `public/` while returning 403 for `.env`, `storage`, `vendor`, `docs` and source files. **This account uses the second option:** the app lives in `$HOME/ck.deskpulse.click` and the root `.htaccess` funnels requests into `public/`.
4. **SSL** — *SSL/TLS Status* → *Run AutoSSL*. Production forces HTTPS and sends HSTS.
5. **Cron** — one entry under *Cron Jobs* (the table above): `* * * * * cd /home/USER/ck.deskpulse.click && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1`
6. **`.env`** — create it inside the app directory from `.env.production.example` and fill the database, mail, PayMongo and `APP_URL` values. Get an `APP_KEY` with `php artisan key:generate --show` and paste it in **before** the first deploy: the deploy caches the config, so a key added afterwards is only picked up by the next deploy (or by `optimize:clear` + `config:cache`). Never upload a local `.env`.

### Route A — Git Version Control + `.cpanel.yml` (recommended)

1. *Files → Git Version Control* → **Clone** `https://github.com/domricamora/coverandkeyssass.git` into a directory **outside** the site root, e.g. `~/repos/coverandkeys`. A private repository needs *Set Up Access to Private Repositories* first.
2. Open the repository's **Pull or Deploy** tab: **Update from Remote** (pulls `master`), then **Deploy HEAD Commit**. That runs `.cpanel.yml`, which:
   - rsyncs the working tree into `$HOME/ck.deskpulse.click`, skipping `.git`, `.env`, `node_modules`, `tests` and `storage/app/public` (live uploads stay in place);
   - recreates the writable `storage/…` and `bootstrap/cache/` directories and fixes their permissions;
   - stops if `.env` is missing, then runs `composer install --no-dev --optimize-autoloader`, `migrate --force`, `optimize:clear`, the four `*:cache` commands, `storage:link` and `queue:restart`.
3. Every update is the same three steps: `git push` locally → **Update from Remote** → **Deploy HEAD Commit**. Only commits reach the server, so commit before you deploy.

Change the target by editing `DEPLOYPATH` at the top of `.cpanel.yml` (verify the real path in *Domains*). If the host lacks `rsync`, use Route B or ask the host to enable it.

**Assets need no Node on the server.** `public/build` is committed, so the Vite output travels with the code. `/public/build` is also in `.gitignore` (a leftover from before the first commit), so after a local `npm run build` the new hashed chunk files are untracked — add them by force:

```bash
npm run build
git add -f public/build
git commit -m "Rebuild assets"
```

### Route B — FTP / SFTP upload (no Git, no Terminal)

`.deploy.env` (gitignored, local only) holds the FTP account for `ck.deskpulse.click`; the FTP root **is** the site root, so the whole app is uploaded into it.

1. Mirror the app with an SFTP client — FileZilla, `lftp mirror -R`, or `curl --ftp-create-dirs`.
2. Upload everything **except**: `.env`, `.env.production`, `.git/`, `node_modules/`, `tests/`, `storage/app/public` (the live uploads) and `public/hot`.
3. FTP cannot run `composer`, `artisan` or the caches. Use cPanel → *Terminal* for the post-upload steps, or ask the host to run them:

   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan optimize:clear && php artisan config:cache route:cache view:cache event:cache
   ```

### Route C — SSH / cPanel Terminal

When *Terminal* is available, it is the documented flow exactly:

```bash
git clone https://github.com/domricamora/coverandkeyssass.git ~/coverandkeys
cd ~/coverandkeys    # first install: the numbered list above, then:
bash deploy.sh       # every update from then on
```

### First deploy, in this order

1. Create `.env` with an `APP_KEY` (server setup, step 6) — the deploy aborts without it.
2. Deploy: **Deploy HEAD Commit**, the FTP mirror, or `bash deploy.sh`.
3. Once, in *Terminal*:

   ```bash
   cd ~/ck.deskpulse.click
   php artisan db:seed --force   # permissions, roles, modules, reference data only
   php artisan superadmin:create "Your Name" you@domain.com
   ```

   `DatabaseSeeder` calls only `PermissionSeeder`, `RoleSeeder` and `ModuleSeeder`. The marketplace and demo-operations seeders are separate classes that refuse to run in production.
4. Smoke test: `/up` returns 200, `/robots.txt` lists the sitemap, sign in as the Super Admin, then make one test-mode booking payment before switching PayMongo to live keys.

Changing `.env` later needs `php artisan optimize:clear && php artisan config:cache route:cache view:cache event:cache`, or simply deploy again — the deploy does exactly that.

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
