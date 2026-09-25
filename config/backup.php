<?php

/*
| Backups (Phase 36) — see docs/BACKUPS.md.
|
| One archive per run in storage/app/backups: database dump, uploaded files,
| optional encrypted .env, manifest with checksums. Rotation keeps the newest
| `keep` archives. `backup:verify` restores the latest archive into a scratch
| database to prove it is usable.
*/

return [
    'path' => storage_path('app/backups'),

    // Newest archives kept locally; older ones are deleted after each run.
    'keep' => (int) env('BACKUP_KEEP', 14),

    // Optional off-site copy: any filesystem disk (e.g. an `s3` or `ftp` disk
    // added to config/filesystems.php). Null keeps backups on this server only.
    'offsite_disk' => env('BACKUP_OFFSITE_DISK'),

    // Encrypts the copy of .env inside the archive (AES-256). Keep this
    // password outside the server (password manager); without it the .env
    // copy is skipped rather than stored in the clear.
    'password' => env('BACKUP_PASSWORD'),

    // Folders under the app root included as files.
    'folders' => ['storage/app/public', 'storage/app/private'],

    // Client binaries (full path on hosts where they are not on PATH).
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
    'mysql' => env('BACKUP_MYSQL', 'mysql'),

    // Scratch database used by backup:verify. On cPanel create it once in the
    // MySQL Databases screen (with the account prefix) and grant the app user.
    'verify_database' => env('BACKUP_VERIFY_DATABASE'),
];
