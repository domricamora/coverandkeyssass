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

// Housekeeping.
\Illuminate\Support\Facades\Schedule::command('queue:prune-failed --hours=720')->daily();
\Illuminate\Support\Facades\Schedule::command('sanctum:prune-expired --hours=24')->daily();
