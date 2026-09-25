# BACKUPS.md

## Phase 36 — Backups and restore (2026-09-26)

Rule: **a backup does not count until it has been restored.** `backup:verify` does a real restore every week, automatically.

### What is backed up

`php artisan backup:run` writes one archive, `storage/app/backups/ck-YYYYmmdd-HHMMSS.zip`:

| Entry | Contents |
|---|---|
| `database.sql` | `mysqldump --single-transaction --routines --triggers`: a consistent snapshot with no table locks |
| `files/storage/app/public/…`, `files/storage/app/private/…` | Uploaded photos and private attachments (maintenance, messaging) |
| `config/.env` | The environment file, **AES-256 encrypted** with `BACKUP_PASSWORD`. If no password is set it is left out, never stored in the clear. |
| `manifest.json` | App, time, database, table list, SHA-256 of the dump, file count, whether `.env` is included |

Code and media committed to git (for example `public/img/demo`) are not in the archive; git is their backup.

Database credentials reach `mysqldump` and `mysql` through a temporary `0600` option file, never the command line.

### Schedule (via the one cron entry, see DEPLOYMENT.md)

| When | Command | Purpose |
|---|---|---|
| Daily 03:00 | `backup:run` | New archive, rotate old ones, copy off-site if configured |
| Sunday 04:00 | `backup:verify` | Restore the newest archive into a scratch database, check the checksum and that every table is present, report row counts, drop the scratch database |

### Rotation and off-site copies

- `BACKUP_KEEP` (default 14) sets how many archives stay on the server. Older ones are deleted after each run.
- **Off-site:** set `BACKUP_OFFSITE_DISK` to any filesystem disk (for example an `s3` or `ftp` disk in `config/filesystems.php`), and each archive is copied to `backups/` there. A server-only backup does not survive losing the server. Configure this before go-live.
- Keep `BACKUP_PASSWORD` in a password manager **off** the server. Without it, the `.env` copy cannot be decrypted.

### Configuration

| Key | Default | Notes |
|---|---|---|
| `BACKUP_KEEP` | 14 | Local archives kept |
| `BACKUP_OFFSITE_DISK` | none | Filesystem disk name |
| `BACKUP_PASSWORD` | none | Encrypts the `.env` copy |
| `BACKUP_MYSQLDUMP`, `BACKUP_MYSQL` | `mysqldump`, `mysql` | Full paths on hosts where they are not on PATH |
| `BACKUP_VERIFY_DATABASE` | `<db>_restore_check` | Scratch database for `backup:verify` |

On cPanel, the app's database user usually can't `CREATE DATABASE`. Create the scratch database once in *MySQL Databases*, for example `acct_ck_restore_check`, give the app user all privileges on it, and set `BACKUP_VERIFY_DATABASE`. It is then reused instead of dropped; the dump's `DROP TABLE IF EXISTS` statements replace the previous run's tables.

### Restore procedure

The restore was tested on 2026-09-26: `backup:verify` on the dev database restored 120 tables and 2,725 rows. `BackupTest` does it on every test run, and also proves a tampered archive is refused.

1. **Pick the archive.** `ls storage/app/backups` or the off-site disk. Copy it into `storage/app/backups/` if it came from off-site.
2. **Prove it first:** `php artisan backup:verify storage/app/backups/ck-….zip`. This touches only the scratch database.
3. **Stop traffic:** `php artisan down`.
4. **Restore:** `php artisan backup:restore storage/app/backups/ck-….zip --force`. This replaces the live database and restores uploaded files. Add `--no-files` to restore the database only.
5. **`.env`, only if the server itself was lost:**
   - Extract `config/.env` with any AES-capable zip tool (7-Zip: `7z x ck-….zip config/.env`) using `BACKUP_PASSWORD`.
   - Review it (hosts and paths may differ on a new server), then copy it into place.
6. **Clear and restart:** `php artisan optimize:clear && php artisan config:cache route:cache view:cache && php artisan queue:restart && php artisan up`.
7. **Check:**
   - `/up` returns 200.
   - Sign in.
   - Open a booking, an order and the accounting overview.
   - Run `php artisan accounting:sync` to re-derive postings.

### New server from scratch

Follow DEPLOYMENT.md steps 1 to 4, **skipping `migrate` and `db:seed`**. Then run steps 4 to 7 above. The dump recreates every table with its data.
