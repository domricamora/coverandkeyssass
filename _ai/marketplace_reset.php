<?php

// Temporary helper: wipe demo marketplace rows so the seeder can be exercised
// from a clean slate. Counter observers are honoured (soft delete first, then
// force delete) and location counters are recomputed.
require __DIR__.'/../vendor/autoload.php';

use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Services\ListingMetricsService;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::table('reviews')->delete();
DB::table('favorites')->delete();
DB::table('media')->delete();

foreach ([Property::class, Restaurant::class] as $class) {
    $class::withoutGlobalScope('tenant')->delete();

    $class::withoutGlobalScope('tenant')->onlyTrashed()->forceDelete();
}

$metrics = app(ListingMetricsService::class);

Location::query()->each(fn (Location $location) => $metrics->refreshLocationCounts($location));

printf(
    "reset: properties=%d restaurants=%d reviews=%d favorites=%d media=%d\n",
    Property::withoutGlobalScope('tenant')->withTrashed()->count(),
    Restaurant::withoutGlobalScope('tenant')->withTrashed()->count(),
    DB::table('reviews')->count(),
    DB::table('favorites')->count(),
    DB::table('media')->count(),
);