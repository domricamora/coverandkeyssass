<?php

use App\Modules\Booking\Providers\BookingServiceProvider;
use App\Modules\Customer\Providers\CustomerServiceProvider;
use App\Modules\Marketplace\Providers\MarketplaceServiceProvider;
use App\Modules\PropertyManagement\Providers\PropertyManagementServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    MarketplaceServiceProvider::class,
    PropertyManagementServiceProvider::class,
    BookingServiceProvider::class,
    CustomerServiceProvider::class,
];

