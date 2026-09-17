<?php

// Temporary diagnostic: confirm morph aliases used by the Marketplace module.
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$models = [
    App\Modules\Marketplace\Models\Property::class,
    App\Modules\Marketplace\Models\Restaurant::class,
    App\Modules\Marketplace\Models\Media::class,
    App\Modules\Marketplace\Models\Review::class,
    App\Modules\Marketplace\Models\Favorite::class,
    App\Models\Tenant::class,
    App\Models\User::class,
];

foreach ($models as $class) {
    printf("%-60s => %s\n", $class, (new $class)->getMorphClass());
}

echo "\nMorph map: ".json_encode(Illuminate\Database\Eloquent\Relations\Relation::morphMap())."\n";