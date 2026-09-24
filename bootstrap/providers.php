<?php

use App\Modules\Marketplace\Providers\MarketplaceServiceProvider;
use App\Modules\PropertyManagement\Providers\PropertyManagementServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    MarketplaceServiceProvider::class,
    PropertyManagementServiceProvider::class,
];

