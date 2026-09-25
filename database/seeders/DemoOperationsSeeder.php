<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Crm\Services\CrmService;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Maintenance\Services\MaintenanceService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\Workforce\Models\Department;
use App\Modules\Workforce\Models\Position;
use App\Modules\Workforce\Services\WorkforceService;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Operational demo data so every dashboard screen has something real to show:
 * bookings in every state (with past stays checked out), staff, inventory,
 * maintenance tickets, restaurant menus and orders, then CRM + accounting sync.
 *
 * Everything goes through the domain services, so locks, room nights, folios,
 * housekeeping tasks and ledger postings are produced by the real code. Past
 * stays are created by moving the clock (Carbon::setTestNow) because the
 * booking engine rightly refuses check-ins in the past.
 *
 * Idempotent per business: a business that already has bookings is skipped.
 */
class DemoOperationsSeeder extends Seeder
{
    /** Menus per restaurant slug: category => [[name, description, price], ...]. */
    private const MENUS = [
        'salt-ember-grill' => [
            'From the grill' => [
                ['Charcoal pork belly', 'Coconut-vinegar glaze, pickled papaya.', 485],
                ['Whole grilled snapper', 'Calamansi butter, burnt garlic rice.', 890],
                ['Beef short rib', 'Twelve-hour braise, finished over coals.', 1150],
            ],
            'Raw & small plates' => [
                ['Tanigue kinilaw', 'Cane vinegar, ginger, chilli, coconut cream.', 420],
                ['Grilled corn', 'Salted egg butter, lime.', 180],
            ],
            'Drinks' => [
                ['Calamansi soda', 'House-made cordial.', 120],
                ['Island white, glass', 'Crisp, from the short list.', 360],
            ],
        ],
        'kalye-coffee-kitchen' => [
            'Coffee' => [
                ['Flat white', 'Benguet arabica, double shot.', 160],
                ['Iced barako latte', 'Batangas liberica, fresh milk.', 175],
                ['Pour-over', 'Single origin, ask the barista.', 190],
            ],
            'Brunch' => [
                ['Longganisa eggs benedict', 'Hollandaise, garlic sourdough.', 395],
                ['Ube pancakes', 'Salted coconut caramel.', 320],
                ['Tapsilog bowl', 'Cured beef, garlic rice, fried egg.', 360],
            ],
        ],
        'cove-catch-seafood-house' => [
            'Today\'s catch' => [
                ['Grilled lapu-lapu', 'Whole grouper, lemongrass, charred lime.', 1280],
                ['Garlic butter prawns', 'Tiger prawns, chilli, toasted garlic.', 780],
                ['Seafood paella', 'For two. Order a day ahead.', 1650],
            ],
            'Sides' => [
                ['Ensaladang mangga', 'Green mango, tomato, bagoong.', 190],
                ['Coconut rice', 'Steamed in pandan.', 110],
            ],
        ],
    ];

    private const STAFF = [
        ['Front Office', 'Guest Relations Officer', 110, ['Carmela Villanueva', 'Rafael Mendoza']],
        ['Housekeeping', 'Room Attendant', 95, ['Liza Bautista', 'Joel Ramos', 'Maricel Aquino']],
        ['Kitchen', 'Line Cook', 105, ['Paolo Fernandez']],
        ['Maintenance', 'Maintenance Technician', 115, ['Dante Castillo']],
    ];

    private const STOCK = [
        // sku, name, unit, reorder level, received qty, unit cost
        ['RICE', 'Jasmine rice', 'kg', 20, 50, 58],
        ['COFFEE', 'Benguet coffee beans', 'kg', 5, 12, 720],
        ['SNAPPER', 'Red snapper', 'kg', 8, 6, 390],
        ['TOWEL', 'Bath towels', 'pc', 40, 120, 210],
        ['SHAMPOO', 'Guest shampoo, 30 ml', 'pc', 200, 150, 14],
        ['WATER', 'Bottled water, 500 ml', 'pc', 120, 480, 11],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('DemoOperationsSeeder is for development only.');
        }

        $context = app(TenantContext::class);
        $guests = User::query()->where('email', 'like', '%@example.test')->where('email', 'not like', 'owner@%')->orderBy('id')->get()->values();

        foreach (Tenant::query()->whereIn('slug', ['aplaya-beach-resort', 'kalye-suite-company', 'nido-cove-escapes'])->get() as $tenant) {
            $owner = User::query()->where('email', 'like', 'owner@%')->whereHas('tenants', fn ($q) => $q->whereKey($tenant->id))->first();

            if (! $owner) {
                continue;
            }

            $context->set($tenant);

            if (Booking::query()->exists()) {
                $this->settleFolios($owner);
                $this->extras($owner, $guests);
                $context->forget();

                continue;
            }

            $this->staff($owner);
            $this->inventory($owner);

            foreach (Property::query()->get() as $i => $property) {
                $this->bookings($property, $guests, $i);
                $this->maintenance($property, $owner, $i);
            }

            foreach (Restaurant::query()->get() as $restaurant) {
                $this->menu($restaurant);
                $this->orders($restaurant, $guests);
            }

            $this->settleFolios($owner);
            $this->extras($owner, $guests);
            app(CrmService::class)->sync();
            $context->forget();
        }

        Artisan::call('accounting:sync');
        $this->command?->info('Demo operations seeded: bookings, staff, inventory, maintenance, menus, orders.');
    }

    private function staff(User $owner): void
    {
        $service = app(WorkforceService::class);

        foreach (self::STAFF as [$departmentName, $positionName, $rate, $people]) {
            $department = Department::query()->firstOrCreate(['name' => $departmentName]);
            $position = Position::query()->firstOrCreate(
                ['name' => $positionName, 'department_id' => $department->id],
                ['hourly_rate' => $rate],
            );

            foreach ($people as $n => $name) {
                $service->hire([
                    'name' => $name,
                    'employment_type' => 'full_time',
                    'department_id' => $department->id,
                    'position_id' => $position->id,
                    'hire_date' => now()->subMonths(6 + $n * 5)->toDateString(),
                ], $owner);
            }
        }
    }

    private function inventory(User $owner): void
    {
        $store = StockLocation::query()->firstOrCreate(['name' => 'Main store']);
        Supplier::query()->firstOrCreate(['name' => 'Island Provisions Co.'], ['contact_name' => 'Nestor Lim', 'phone' => '+63 917 204 5518']);
        $service = app(InventoryService::class);

        foreach (self::STOCK as [$sku, $name, $unit, $reorder, $qty, $cost]) {
            $item = InventoryItem::query()->firstOrCreate(['sku' => $sku], ['name' => $name, 'unit' => $unit, 'reorder_level' => $reorder]);
            $service->receive($item, $store, $qty, $cost, $owner, 'Opening stock');
        }
    }

    /**
     * Two finished stays, one in-house guest, two upcoming confirmed stays and
     * one marketplace request per property, staggered by property index so
     * the calendar is not a wall of identical blocks.
     */
    private function bookings(Property $property, $guests, int $i): void
    {
        $types = $property->roomTypes()->active()->get();

        if ($types->isEmpty() || $guests->isEmpty()) {
            return;
        }

        $service = app(BookingService::class);
        $book = function (int $startOffset, int $nights, int $n, string $source = Booking::SOURCE_MANUAL) use ($service, $property, $types, $guests, $i) {
            $user = $guests[($i * 3 + $n) % $guests->count()];

            return $service->reserve($property, [
                'check_in' => today()->addDays($startOffset)->toDateString(),
                'check_out' => today()->addDays($startOffset + $nights)->toDateString(),
                'rooms' => [['room_type_id' => $types[$n % $types->count()]->id, 'quantity' => 1]],
                'adults' => 2,
                'guest_name' => $user->name,
                'guest_email' => $user->email,
            ], $source, $user);
        };

        // Finished stays: travel to the arrival day, check in, travel to departure, check out.
        foreach ([[-24 + $i, 3, 0], [-12 + $i, 2, 1]] as [$start, $nights, $n]) {
            Carbon::setTestNow(today()->addDays($start)->setTime(10, 0));
            $booking = $book(0, $nights, $n);
            $service->transition($booking, Booking::CHECKED_IN);
            Carbon::setTestNow(now()->addDays($nights)->setTime(11, 0));
            $service->transition($booking->refresh(), Booking::CHECKED_OUT);
            Carbon::setTestNow();
        }

        // In house since yesterday.
        Carbon::setTestNow(today()->subDay()->setTime(14, 0));
        $service->transition($book(0, 3, 2), Booking::CHECKED_IN);
        Carbon::setTestNow();

        $book(4 + $i, 3, 3);
        $book(11 + $i, 2, 4);
        $book(19 + $i, 4, 5, Booking::SOURCE_MARKETPLACE);
    }

    /**
     * Guests pay: finished stays settle in full by card, in-house guests have a
     * 50% cash deposit. Only stays with no payment yet, so re-runs are safe.
     */
    private function settleFolios(User $owner): void
    {
        $folio = app(\App\Modules\Folio\Services\FolioService::class);

        foreach (Booking::query()->whereIn('status', [Booking::CHECKED_OUT, Booking::CHECKED_IN])->get() as $booking) {
            $folio->sync($booking);
            $totals = $folio->totals($booking);

            if ($totals['payments'] > 0 || $totals['balance'] <= 0) {
                continue;
            }

            $checkedOut = $booking->status === Booking::CHECKED_OUT;
            $folio->recordPayment(
                $booking,
                $checkedOut ? 'card' : 'cash',
                $checkedOut ? $totals['balance'] : round($totals['balance'] / 2, 2),
                $owner,
                $checkedOut ? 'Settled at check-out' : 'Deposit at check-in',
            );
        }
    }

    /**
     * More of the platform, each part idempotent on its own so re-running the
     * seeder fills in whatever an older demo database is missing.
     */
    private function extras(User $owner, $guests): void
    {
        $this->dining($guests);
        $this->deliverySetup();
        $this->promotions();
        $this->loyaltyProgram();
        $this->campaigns($owner);
        $this->rota($owner);
        $this->purchasing($owner);
        $this->conversations($owner, $guests);
    }

    /** Dining areas + tables, then upcoming table reservations for reservable restaurants. */
    private function dining($guests): void
    {
        foreach (Restaurant::query()->get() as $restaurant) {
            if (\App\Modules\RestaurantManagement\Models\RestaurantTable::query()->where('restaurant_id', $restaurant->id)->exists()) {
                continue;
            }

            foreach ([['Main dining room', ['T1' => 2, 'T2' => 2, 'T3' => 4, 'T4' => 4, 'T5' => 6]], ['Terrace', ['P1' => 2, 'P2' => 4, 'P3' => 8]]] as $sort => [$areaName, $tables]) {
                $area = \App\Modules\RestaurantManagement\Models\DiningArea::query()->create(['restaurant_id' => $restaurant->id, 'name' => $areaName, 'sort_order' => $sort]);
                foreach ($tables as $label => $seats) {
                    \App\Modules\RestaurantManagement\Models\RestaurantTable::query()->create(['restaurant_id' => $restaurant->id, 'dining_area_id' => $area->id, 'label' => $label, 'seats' => $seats, 'status' => 'active']);
                }
            }

            if (! $restaurant->reservations_enabled || $guests->isEmpty()) {
                continue;
            }

            $service = app(\App\Modules\RestaurantManagement\Services\ReservationService::class);
            foreach ([[1, '19:00', 2], [1, '19:30', 4], [2, '12:30', 3], [3, '20:00', 6], [5, '18:30', 2]] as $n => [$days, $time, $party]) {
                $guest = $guests[($n + 1) % $guests->count()];
                rescue(fn () => $service->reserve($restaurant, [
                    'date' => today()->addDays($days)->toDateString(),
                    'time' => $time,
                    'party_size' => $party,
                    'guest_name' => $guest->name,
                    'guest_email' => $guest->email,
                    'special_requests' => $n === 0 ? 'Anniversary dinner, a quiet table please.' : null,
                ], \App\Modules\RestaurantManagement\Models\TableReservation::SOURCE_MARKETPLACE, $guest), report: false);
            }
        }
    }

    private function deliverySetup(): void
    {
        foreach (Restaurant::query()->where('delivery_enabled', true)->get() as $restaurant) {
            if (\App\Modules\Delivery\Models\DeliveryZone::query()->where('restaurant_id', $restaurant->id)->exists()) {
                continue;
            }

            foreach ([['Town centre', 3, 49, 300, 30], ['Beachfront strip', 6, 89, 500, 45]] as $sort => [$name, $km, $fee, $min, $eta]) {
                \App\Modules\Delivery\Models\DeliveryZone::query()->create(['restaurant_id' => $restaurant->id, 'name' => $name, 'radius_km' => $km, 'fee' => $fee, 'min_order' => $min, 'free_over' => 1500, 'eta_minutes' => $eta, 'is_active' => true, 'sort_order' => $sort]);
            }
        }

        if (Restaurant::query()->where('delivery_enabled', true)->exists() && ! \App\Modules\Delivery\Models\Driver::query()->exists()) {
            \App\Modules\Delivery\Models\Driver::query()->create(['name' => 'Arnel Dizon', 'phone' => '+63 917 330 1142', 'vehicle' => 'Motorbike', 'is_active' => true]);
            \App\Modules\Delivery\Models\Driver::query()->create(['name' => 'Jomar Pascual', 'phone' => '+63 918 204 7710', 'vehicle' => 'Motorbike', 'is_active' => true]);
        }
    }

    private function promotions(): void
    {
        if (\App\Modules\Booking\Models\Promotion::query()->exists()) {
            return;
        }

        $promo = \App\Modules\Booking\Models\Promotion::class;
        $promo::query()->create(['code' => 'RAINYDAYS', 'name' => 'Rainy season stays', 'type' => $promo::TYPE_PERCENT, 'value' => 15, 'starts_on' => today(), 'ends_on' => today()->addMonths(2), 'min_nights' => 2, 'max_uses' => 100, 'is_active' => true, 'applies_to' => $promo::FOR_STAYS]);

        if ($restaurant = Restaurant::query()->first()) {
            $promo::query()->create(['code' => 'FIRSTBITE', 'name' => 'First order ₱150 off', 'type' => $promo::TYPE_FIXED, 'value' => 150, 'starts_on' => today(), 'ends_on' => today()->addMonth(), 'max_uses' => 200, 'is_active' => true, 'applies_to' => $promo::FOR_ORDERS, 'restaurant_id' => $restaurant->id, 'min_subtotal' => 600]);
        }
    }

    private function loyaltyProgram(): void
    {
        \App\Modules\Loyalty\Models\LoyaltyProgram::query()->firstOrCreate([], ['enabled' => true, 'pesos_per_point' => 100, 'referral_points' => 200]);

        if (\App\Modules\Loyalty\Models\Reward::query()->exists()) {
            return;
        }

        foreach ([['Welcome drink', 40, 150], ['₱500 stay credit', 150, 500], ['₱1,500 stay credit', 400, 1500]] as [$name, $cost, $credit]) {
            \App\Modules\Loyalty\Models\Reward::query()->create(['name' => $name, 'points_cost' => $cost, 'kind' => 'credit', 'credit_amount' => $credit, 'is_active' => true]);
        }
    }

    private function campaigns(User $owner): void
    {
        if (\App\Modules\Marketing\Models\Campaign::query()->exists()) {
            return;
        }

        \App\Modules\Marketing\Models\Campaign::query()->create(['name' => 'Rainy season getaway', 'channel' => 'email', 'audience' => 'all', 'subject' => '15% off two nights or more', 'body' => "Hi {name},\n\nRainy season is the quiet season. Book two nights or more with code RAINYDAYS for 15% off.\n\n{business}", 'status' => 'draft', 'created_by' => $owner->id]);
        \App\Modules\Marketing\Models\Campaign::query()->create(['name' => 'Weekend brunch launch', 'channel' => 'email', 'audience' => 'all', 'subject' => 'Brunch is back on weekends', 'body' => "Hi {name},\n\nWeekend brunch starts this Saturday. See you there.\n\n{business}", 'status' => 'draft', 'created_by' => $owner->id]);
    }

    /** Next week's rota for every employee: day or evening shifts, one day off. */
    private function rota(User $owner): void
    {
        if (\App\Modules\Workforce\Models\Shift::query()->exists()) {
            return;
        }

        $service = app(WorkforceService::class);
        $propertyId = Property::query()->value('id');

        foreach (\App\Modules\Workforce\Models\Employee::query()->get() as $e => $employee) {
            foreach (range(1, 6) as $day) {
                if (($day + $e) % 7 === 0) {
                    continue;
                }
                $date = today()->addDays($day)->toDateString();
                [$from, $to] = $e % 2 === 0 ? ['07:00', '15:00'] : ['14:00', '22:00'];
                rescue(fn () => $service->scheduleShift($employee, "{$date} {$from}", "{$date} {$to}", $owner, $propertyId), report: false);
            }
        }
    }

    private function purchasing(User $owner): void
    {
        if (\App\Modules\Inventory\Models\PurchaseOrder::query()->exists()) {
            return;
        }

        $supplier = Supplier::query()->first();
        $store = StockLocation::query()->first();
        $items = InventoryItem::query()->whereIn('sku', ['SNAPPER', 'SHAMPOO', 'COFFEE'])->get();

        if ($supplier && $store && $items->isNotEmpty()) {
            app(InventoryService::class)->createPurchaseOrder($supplier, $store, $items->map(fn ($item) => [
                'inventory_item_id' => $item->id,
                'quantity' => $item->sku === 'SHAMPOO' ? 200 : 10,
                'unit_cost' => $item->sku === 'SHAMPOO' ? 14 : ($item->sku === 'COFFEE' ? 720 : 390),
            ])->all(), $owner, today()->addDays(3)->toDateString(), 'Weekly restock');
        }
    }

    /** Guest questions to the business with the owner's replies. */
    private function conversations(User $owner, $guests): void
    {
        if (\App\Modules\Messaging\Models\Thread::query()->where('tenant_id', app(TenantContext::class)->id())->exists() || $guests->isEmpty()) {
            return;
        }

        $messaging = app(\App\Modules\Messaging\Services\MessagingService::class);
        $property = Property::query()->first();
        $threads = [
            ['Early check-in?', 'Hi! Our flight lands at 8am. Is there any chance of an early check-in?', 'Good morning! We will do our best. The room is usually ready by noon, and you are welcome to leave your bags and use the pool before then.'],
            ['Airport transfer', 'Do you offer transfers from the airport? We are a family of four with two surfboards.', 'Yes, we can arrange a van that fits boards. It is ₱1,800 each way; just send us your flight number.'],
        ];

        foreach ($threads as $n => [$subject, $question, $reply]) {
            $guest = $guests[$n % $guests->count()];
            $thread = rescue(fn () => $messaging->startWithBusiness($guest, $property, $subject, $question), report: false);
            if ($thread) {
                rescue(fn () => $messaging->post($thread, $owner, $reply), report: false);
            }
        }
    }

    private function maintenance(Property $property, User $owner, int $i): void
    {
        $rooms = $property->rooms()->orderBy('room_number')->get();
        $service = app(MaintenanceService::class);

        $service->open($property, $rooms->get(1), [
            'title' => 'Aircon dripping onto the desk',
            'description' => 'Guest reported water on the desk in the evening; drain line likely blocked.',
            'category' => 'hvac',
            'priority' => 'high',
        ], $owner);

        if ($i === 0) {
            $service->open($property, $rooms->last(), ['title' => 'Shower mixer loose', 'category' => 'plumbing'], $owner);
        }
    }

    private function menu(Restaurant $restaurant): void
    {
        $restaurant->forceFill(['ordering_enabled' => true])->save();

        $categorySort = 0;
        foreach (self::MENUS[$restaurant->slug] ?? [] as $categoryName => $items) {
            $category = MenuCategory::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $categoryName],
                ['is_active' => true, 'sort_order' => $categorySort++],
            );

            foreach ($items as $itemSort => [$name, $description, $price]) {
                MenuItem::query()->firstOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $name],
                    [
                        'menu_category_id' => $category->id,
                        'description' => $description,
                        'price' => $price,
                        'currency' => 'PHP',
                        'is_available' => true,
                        'sort_order' => $itemSort,
                    ],
                );
            }
        }
    }

    /** Three completed pickup orders, one in the kitchen and one just placed. */
    private function orders(Restaurant $restaurant, $guests): void
    {
        $items = MenuItem::query()->where('restaurant_id', $restaurant->id)->pluck('id');

        if ($items->count() < 2 || $guests->isEmpty()) {
            return;
        }

        $service = app(OrderService::class);
        $completed = [Order::ACCEPTED, Order::PREPARING, Order::READY, Order::COMPLETED];
        $flows = [$completed, $completed, $completed, [Order::ACCEPTED, Order::PREPARING], []];

        foreach ($flows as $n => $states) {
            $guest = $guests[($n + 2) % $guests->count()];
            $order = $service->place($restaurant, [
                ['item_id' => $items[$n % $items->count()], 'quantity' => 1 + ($n % 2)],
                ['item_id' => $items[($n + 1) % $items->count()], 'quantity' => 1],
            ], [
                'fulfillment' => Order::PICKUP,
                'payment_method' => Order::PAY_CASH,
                'customer_name' => $guest->name,
                'customer_phone' => '+63 917 55'.(1000 + $n * 37),
            ], $guest);

            foreach ($states as $state) {
                $order = $service->transition($order->refresh(), $state);
            }
        }
    }
}
