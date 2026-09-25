<?php

use App\Modules\Accounting\Providers\AccountingServiceProvider;
use App\Modules\Booking\Providers\BookingServiceProvider;
use App\Modules\Crm\Providers\CrmServiceProvider;
use App\Modules\Customer\Providers\CustomerServiceProvider;
use App\Modules\Delivery\Providers\DeliveryServiceProvider;
use App\Modules\Folio\Providers\FolioServiceProvider;
use App\Modules\Housekeeping\Providers\HousekeepingServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Maintenance\Providers\MaintenanceServiceProvider;
use App\Modules\Workforce\Providers\WorkforceServiceProvider;
use App\Modules\Marketplace\Providers\MarketplaceServiceProvider;
use App\Modules\Ordering\Providers\OrderingServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;
use App\Modules\Pos\Providers\PosServiceProvider;
use App\Modules\PropertyManagement\Providers\PropertyManagementServiceProvider;
use App\Modules\RestaurantManagement\Providers\RestaurantManagementServiceProvider;
use App\Modules\Wallet\Providers\WalletServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    MarketplaceServiceProvider::class,
    PropertyManagementServiceProvider::class,
    BookingServiceProvider::class,
    CustomerServiceProvider::class,
    PaymentsServiceProvider::class,
    WalletServiceProvider::class,
    RestaurantManagementServiceProvider::class,
    OrderingServiceProvider::class,
    DeliveryServiceProvider::class,
    FolioServiceProvider::class,
    HousekeepingServiceProvider::class,
    MaintenanceServiceProvider::class,
    WorkforceServiceProvider::class,
    InventoryServiceProvider::class,
    PosServiceProvider::class,
    AccountingServiceProvider::class,
    CrmServiceProvider::class,
];

