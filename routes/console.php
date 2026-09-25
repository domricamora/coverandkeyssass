<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Production schedule (Phase 35) — one cron entry drives everything:
|   * * * * * cd /home/USER/app && php artisan schedule:run >> /dev/null 2>&1
|--------------------------------------------------------------------------
*/

// Campaign sends and marketing automations (abandoned carts, review requests…).
\Illuminate\Support\Facades\Schedule::command('marketing:run')->everyFifteenMinutes()->withoutOverlapping();

// Subscription renewals, past-due handling, trial expiry, restore on payment.
\Illuminate\Support\Facades\Schedule::command('billing:run')->dailyAt('01:00')->withoutOverlapping()->onOneServer();

// Full accounting backstop (report screens only sync live / recent folios).
\Illuminate\Support\Facades\Schedule::command('accounting:sync')->dailyAt('02:00')->withoutOverlapping()->onOneServer();

// Warn owners before module trials end.
\Illuminate\Support\Facades\Schedule::command('notifications:trials')->dailyAt('08:00')->onOneServer();

// Queue worker for shared hosting (no supervisor): drain the queue each minute
// and exit before the next run. On a VPS with supervisor, run `queue:work`
// under supervisor instead and remove this entry.
\Illuminate\Support\Facades\Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();

// Backups (Phase 36): nightly archive + weekly proof that it restores.
\Illuminate\Support\Facades\Schedule::command('backup:run')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
\Illuminate\Support\Facades\Schedule::command('backup:verify')->weeklyOn(0, '04:00')->withoutOverlapping()->onOneServer();

Artisan::command('backup:run', function (\App\Support\BackupManager $backups) {
    $archive = $backups->create();
    $this->info('Backup written: '.$archive.' ('.number_format(filesize($archive) / 1048576, 1).' MB)');
})->purpose('Back up the database, uploaded files and (encrypted) .env; rotate old archives');

Artisan::command('backup:verify {archive?}', function (\App\Support\BackupManager $backups) {
    $counts = $backups->verify($this->argument('archive'));
    $this->info('Restore verified: '.count($counts).' tables, '.array_sum($counts).' rows restored into the scratch database.');
})->purpose('Restore the latest (or given) backup into a scratch database to prove it is usable');

Artisan::command('backup:restore {archive} {--force} {--no-files}', function (\App\Support\BackupManager $backups) {
    if (! $this->option('force')) {
        $this->error('This overwrites the live database'.($this->option('no-files') ? '' : ' and uploaded files').'. Re-run with --force.');

        return 1;
    }

    $manifest = $backups->restore($this->argument('archive'), withFiles: ! $this->option('no-files'));
    $this->info('Restored backup from '.$manifest['created_at'].'. Run: php artisan optimize:clear && php artisan queue:restart');
})->purpose('Restore the database (and files) from a backup archive — destructive, needs --force');

// Housekeeping.
\Illuminate\Support\Facades\Schedule::command('queue:prune-failed --hours=720')->daily();
\Illuminate\Support\Facades\Schedule::command('sanctum:prune-expired --hours=24')->daily();
