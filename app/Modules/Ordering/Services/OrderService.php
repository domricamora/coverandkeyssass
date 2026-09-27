<?php

namespace App\Modules\Ordering\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\Promotion;
use App\Modules\PropertyManagement\Models\Room;
use Illuminate\Database\Eloquent\Collection;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Delivery\Models\Driver;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Events\OrderLinesAdded;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Events\OrderTransitioned;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Modules\Ordering\Events\OrderTransitioning;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Notifications\OrderStatusChanged;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\RestaurantManagement\Models\ModifierOption;
use App\Support\AuditLogger;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Food ordering (Phase 11): pricing, placing orders and the order state
 * machine. Prices are always recomputed on the server from the menu —
 * MenuItem::priceWith() for each line — never taken from the client.
 *
 * Callers on the marketplace / customer side run these inside the
 * restaurant's tenant (TenantContext::runAs).
 */
class OrderService
{
    public const MAX_QUANTITY = 50;

    // ponytail: flat walk-to-room time; per-property setting if resorts ask.
    public const ROOM_SERVICE_MINUTES = 10;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ModuleService $modules,
    ) {}

    public function acceptsOrders(Restaurant $restaurant): bool
    {
        if (! $restaurant->ordering_enabled || ! $restaurant->isPublished()) {
            return false;
        }

        $module = Module::query()->where('slug', 'restaurant')->first();
        $tenant = Tenant::query()->find($restaurant->tenant_id);

        return $module && $tenant && $tenant->status === 'active' && $this->modules->isEnabled($module, $tenant);
    }

    /**
     * Price cart lines and the order totals.
     *
     * @param  list<array{item_id: int|string, option_ids?: list<int|string>, quantity: int|string, notes?: ?string}>  $lines
     * @return array{lines: list<array>, subtotal: float, discount: float, tax: float, delivery_fee: float, total: float, promotion: ?Promotion}
     */
    public function quote(Restaurant $restaurant, array $lines, ?string $promoCode = null, ?DeliveryZone $zone = null): array
    {
        $priced = [];

        foreach ($lines as $line) {
            $item = $restaurant->menuItems()->with('category')->find((int) $line['item_id']);

            if (! $item || ! $item->is_available || ! $item->category?->is_active) {
                $this->fail('cart', ($item?->name ?? 'An item').' is no longer available. Please remove it from your cart.');
            }

            $quantity = (int) $line['quantity'];
            if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
                $this->fail('cart', 'Quantities must be between 1 and '.self::MAX_QUANTITY.'.');
            }

            $optionIds = array_map('intval', $line['option_ids'] ?? []);
            $unit = (float) $item->priceWith($optionIds);

            $priced[] = [
                'item' => $item,
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'modifiers' => $this->modifierSnapshot($optionIds),
                'unit_price' => $unit,
                'quantity' => $quantity,
                'line_total' => round($unit * $quantity, 2),
                'notes' => $line['notes'] ?? null,
            ];
        }

        $subtotal = round(array_sum(array_column($priced, 'line_total')), 2);
        $promotion = $this->promotionFor($restaurant, $promoCode, $subtotal);
        $discount = $promotion?->discountOn($subtotal) ?? 0.0;
        $taxable = $subtotal - $discount;
        $deliveryFee = $zone?->feeFor($taxable) ?? 0.0;
        $rate = (float) $restaurant->tax_rate / 100;

        // Inclusive (PH menu prices usually include VAT): tax is the share already inside the price.
        $tax = $restaurant->tax_inclusive
            ? round($taxable - $taxable / (1 + $rate), 2)
            : round($taxable * $rate, 2);
        $total = round($taxable + ($restaurant->tax_inclusive ? 0 : $tax) + $deliveryFee, 2);

        return compact('subtotal', 'discount', 'tax', 'total', 'promotion') + ['lines' => $priced, 'delivery_fee' => $deliveryFee];
    }

    /**
     * @param  array{fulfillment: string, payment_method: string, customer_name: string, customer_phone?: ?string,
     *               delivery_address?: ?string, notes?: ?string, promo_code?: ?string}  $data
     */
    public function place(Restaurant $restaurant, array $lines, array $data, ?User $customer): Order
    {
        if ($lines === []) {
            $this->fail('cart', 'Your cart is empty.');
        }

        match (true) {
            ! $this->acceptsOrders($restaurant) => $this->fail('cart', 'This restaurant is not taking online orders right now.'),
            $data['fulfillment'] === Order::DELIVERY && ! $restaurant->delivery_enabled => $this->fail('fulfillment', 'This restaurant does not deliver.'),
            $data['fulfillment'] === Order::DELIVERY && blank($data['delivery_address'] ?? null) => $this->fail('delivery_address', 'Enter a delivery address.'),
            $data['payment_method'] === Order::PAY_ONLINE && ! PaymentService::enabled() => $this->fail('payment_method', 'Online payment is not available right now.'),
            $data['fulfillment'] === Order::ROOM_SERVICE && ! $restaurant->room_service_enabled => $this->fail('fulfillment', 'This restaurant does not offer room service.'),
            $data['payment_method'] === Order::PAY_ROOM && $data['fulfillment'] !== Order::ROOM_SERVICE => $this->fail('payment_method', 'Only room service orders can be charged to a room.'),
            default => null,
        };

        $zone = $data['fulfillment'] === Order::DELIVERY ? $this->deliveryZone($restaurant, $data) : null;
        [$booking, $room] = $data['fulfillment'] === Order::ROOM_SERVICE ? $this->roomServiceStay($restaurant, $customer, $data) : [null, null];

        if ($room) {
            $data['delivery_address'] = 'Room '.$room->label().' · '.$booking->property->name;
        }
        $scheduledFor = $this->scheduledFor($restaurant, $data['scheduled_for'] ?? null);

        $order = DB::transaction(function () use ($restaurant, $lines, $data, $customer, $zone, $scheduledFor, $booking, $room) {
            $quote = $this->quote($restaurant, $lines, $data['promo_code'] ?? null, $zone);

            if ($zone?->min_order !== null && $quote['subtotal'] < (float) $zone->min_order) {
                $this->fail('delivery_zone_id', 'Delivery to '.$zone->name.' needs an order of at least '.\App\Support\Currency::symbol().number_format((float) $zone->min_order, 2).'.');
            }

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $customer?->id,
                'promotion_id' => $quote['promotion']?->id,
                'reference' => Order::newReference(),
                'status' => Order::PENDING,
                'fulfillment' => $data['fulfillment'],
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_method'] === Order::PAY_ROOM ? Order::CHARGED : Order::UNPAID,
                'booking_id' => $booking?->id,
                'room_id' => $room?->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'delivery_address' => ($zone || $room) ? $data['delivery_address'] : null,
                'delivery_zone_id' => $zone?->id,
                'delivery_lat' => $zone ? ($data['delivery_lat'] ?? null) : null,
                'delivery_lng' => $zone ? ($data['delivery_lng'] ?? null) : null,
                'scheduled_for' => $scheduledFor,
                'notes' => $data['notes'] ?? null,
                'currency' => $quote['lines'][0]['item']->currency ?? 'PHP',
                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount'],
                'tax_rate' => $restaurant->tax_rate,
                'tax_inclusive' => $restaurant->tax_inclusive,
                'tax_total' => $quote['tax'],
                'delivery_fee' => $quote['delivery_fee'],
                'total' => $quote['total'],
            ]);

            foreach ($quote['lines'] as $line) {
                $order->items()->create(collect($line)->except('item')->all());
            }

            $quote['promotion']?->redeem($order->reference);

            return $order;
        });

        $this->audit->log('order.placed', $order, null, [
            'reference' => $order->reference,
            'total' => $order->total,
            'payment_method' => $order->payment_method,
        ]);

        OrderPlaced::dispatch($order);

        return $order;
    }

    public function transition(Order $order, string $to, ?string $reason = null): Order
    {
        if (! $order->canTransitionTo($to)) {
            $this->fail('status', 'A '.strtolower($order->statusLabel()).' order cannot be marked '.str_replace('_', ' ', $to).'.');
        }

        if ($to === Order::ACCEPTED && $order->payment_method === Order::PAY_ONLINE && $order->payment_status !== Order::PAID) {
            $this->fail('status', 'This order is waiting for the online payment.');
        }

        // A dine-in check closes only once it is settled.
        if ($to === Order::COMPLETED && $order->fulfillment === Order::DINE_IN && ! in_array($order->payment_status, [Order::PAID, Order::CHARGED], true)) {
            $this->fail('status', 'Settle the bill before closing this table.');
        }

        $from = $order->status;

        // Listeners may veto (the PayMongo refund throws when it fails).
        OrderTransitioning::dispatch($order, $to);

        if ($to === Order::OUT_FOR_DELIVERY && $order->fulfillment === Order::DELIVERY && ! $order->driver_id) {
            $this->fail('driver_id', 'Assign a driver before dispatching the order.');
        }

        // Ride time: the zone's for deliveries, a walk to the room for room service.
        $zoneEta = match ($order->fulfillment) {
            Order::DELIVERY => (int) ($order->zone?->eta_minutes ?? 30),
            Order::ROOM_SERVICE => self::ROOM_SERVICE_MINUTES,
            default => 0,
        };

        $order->status = $to;
        match ($to) {
            // ETA: the scheduled time, or kitchen prep (+ the ride for deliveries).
            Order::ACCEPTED => $order->forceFill([
                'accepted_at' => now(),
                'estimated_at' => $order->scheduled_for ?? now()->addMinutes((int) $order->restaurant->prep_minutes + $zoneEta),
            ]),
            Order::READY => $order->forceFill(['ready_at' => now()]),
            Order::OUT_FOR_DELIVERY => $order->forceFill(['dispatched_at' => now(), 'estimated_at' => now()->addMinutes($zoneEta)]),
            Order::DELIVERED => $order->forceFill(['delivered_at' => now()]),
            Order::COMPLETED => $order->forceFill(['completed_at' => now()]),
            Order::CANCELLED => $order->forceFill(['cancelled_at' => now(), 'cancellation_reason' => $reason]),
            Order::REFUNDED => $order->forceFill(['payment_status' => 'refunded']),
            default => null,
        };
        $order->save();

        $this->audit->log('order.'.$to, $order, ['status' => $from], ['status' => $to, 'reason' => $reason]);

        OrderTransitioned::dispatch($order, $from, $to);

        $order->customer?->notify(new OrderStatusChanged($order));

        return $order;
    }

    // ------------------------------------------------------------------
    // Point of sale (Phase 19)
    // ------------------------------------------------------------------

    /**
     * A dine-in (or walk-up) ticket rung up at the register. It skips the
     * online-ordering switches, goes straight to the kitchen (accepted →
     * stock is used) and is paid later through PosService.
     */
    public function placeAtRegister(Restaurant $restaurant, array $lines, ?RestaurantTable $table, User $staff, ?string $customerName = null, ?string $notes = null): Order
    {
        if ($lines === []) {
            $this->fail('lines', 'Add at least one item.');
        }

        if ($table && ((int) $table->restaurant_id !== (int) $restaurant->id || $table->status !== RestaurantTable::STATUS_ACTIVE)) {
            $this->fail('restaurant_table_id', 'Pick an active table of this restaurant.');
        }

        $order = DB::transaction(function () use ($restaurant, $lines, $table, $customerName, $notes) {
            $quote = $this->quote($restaurant, $lines);

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'restaurant_table_id' => $table?->id,
                'reference' => Order::newReference(),
                'channel' => Order::CHANNEL_POS,
                'status' => Order::PENDING,
                'fulfillment' => $table ? Order::DINE_IN : Order::PICKUP,
                'payment_method' => Order::PAY_POS,
                'payment_status' => Order::UNPAID,
                'customer_name' => $customerName ?: ($table ? 'Table '.$table->label : 'Walk-in'),
                'notes' => $notes,
                'currency' => $quote['lines'][0]['item']->currency ?? 'PHP',
                'subtotal' => $quote['subtotal'],
                'tax_rate' => $restaurant->tax_rate,
                'tax_inclusive' => $restaurant->tax_inclusive,
                'tax_total' => $quote['tax'],
                'total' => $quote['total'],
            ]);

            foreach ($quote['lines'] as $line) {
                $order->items()->create(collect($line)->except('item')->all());
            }

            return $order;
        });

        $this->audit->log('order.placed', $order, null, ['reference' => $order->reference, 'channel' => 'pos', 'staff' => $staff->id, 'total' => $order->total]);

        return $this->transition($order, Order::ACCEPTED);
    }

    /** Add lines to an open, unpaid register ticket; new lines reach the kitchen (and stock) at once. */
    public function addLines(Order $order, array $lines): Order
    {
        $this->mustBeOpenTicket($order);

        DB::transaction(function () use ($order, $lines): void {
            foreach ($this->quote($order->restaurant, $lines)['lines'] as $line) {
                $order->items()->create(collect($line)->except('item')->all());
            }

            $this->recalculate($order);
        });

        OrderLinesAdded::dispatch($order);

        return $order;
    }

    /** Manual discount at the register (percent or fixed) with a reason. */
    public function applyDiscount(Order $order, string $type, float $value, string $reason): Order
    {
        $this->mustBeOpenTicket($order);

        $subtotal = (float) $order->items()->sum('line_total');
        $discount = $type === 'percent' ? round($subtotal * min(100, max(0, $value)) / 100, 2) : min($subtotal, max(0, $value));

        $order->forceFill(['discount_total' => $discount, 'discount_reason' => $reason])->save();
        $this->recalculate($order);

        $this->audit->log('order.discounted', $order, null, ['type' => $type, 'value' => $value, 'amount' => $discount, 'reason' => $reason]);

        return $order;
    }

    /** Re-total a ticket from its lines, the order's tax snapshot and its discount. */
    private function recalculate(Order $order): void
    {
        $subtotal = round((float) $order->items()->sum('line_total'), 2);
        $discount = min((float) $order->discount_total, $subtotal);
        $taxable = $subtotal - $discount;
        $rate = (float) $order->tax_rate / 100;
        $tax = $order->tax_inclusive ? round($taxable - $taxable / (1 + $rate), 2) : round($taxable * $rate, 2);

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'total' => round($taxable + ($order->tax_inclusive ? 0 : $tax) + (float) $order->delivery_fee, 2),
        ])->save();
    }

    private function mustBeOpenTicket(Order $order): void
    {
        if ($order->channel !== Order::CHANNEL_POS || $order->payment_status !== Order::UNPAID || ! in_array($order->status, [Order::ACCEPTED, Order::PREPARING, Order::READY], true)) {
            $this->fail('order', 'Only open, unpaid register tickets can be changed.');
        }
    }

    /** Assign (or clear) the driver of a delivery order that is still in the kitchen or on the road. */
    public function assignDriver(Order $order, ?Driver $driver): Order
    {
        if ($order->fulfillment !== Order::DELIVERY || ! in_array($order->status, [Order::ACCEPTED, Order::PREPARING, Order::READY, Order::OUT_FOR_DELIVERY], true)) {
            $this->fail('driver_id', 'Drivers can only be assigned to accepted delivery orders.');
        }

        if ($driver && ! $driver->is_active) {
            $this->fail('driver_id', $driver->name.' is not active.');
        }

        if (! $driver && $order->status === Order::OUT_FOR_DELIVERY) {
            $this->fail('driver_id', 'This order is already on the road.');
        }

        $was = $order->driver_id;
        $order->forceFill(['driver_id' => $driver?->id])->save();

        $this->audit->log('order.driver_assigned', $order, ['driver_id' => $was], ['driver_id' => $driver?->id]);

        return $order;
    }

    /** Called by PaymentService inside its markPaid transaction. */
    public function markPaid(Order $order): void
    {
        $order->forceFill(['payment_status' => Order::PAID])->save();

        if ($order->status !== Order::PENDING) {
            // e.g. cancelled while the customer was paying — host must refund.
            $this->audit->log('order.paid_on_inactive_order', $order, null, ['status' => $order->status]);
        }
    }

    /** @param list<int> $optionIds */
    private function modifierSnapshot(array $optionIds): array
    {
        if ($optionIds === []) {
            return [];
        }

        return ModifierOption::query()->with('group')->whereIn('id', $optionIds)->get()
            ->map(fn (ModifierOption $o) => ['group' => $o->group->name, 'name' => $o->name, 'price' => (string) $o->price])
            ->values()
            ->all();
    }

    /**
     * The customer's checked-in stays at a property of this restaurant's
     * business — the rooms room service can deliver to. Runs inside the
     * restaurant's tenant.
     *
     * @return Collection<int, Booking>
     */
    public function roomServiceStays(Restaurant $restaurant, ?User $customer): Collection
    {
        if (! $customer || ! $restaurant->room_service_enabled) {
            return new Collection;
        }

        return Booking::forCustomer($customer)
            ->where('tenant_id', $restaurant->tenant_id)
            ->where('status', Booking::CHECKED_IN)
            ->with(['property', 'rooms.room'])
            ->get();
    }

    /** @return array{0: Booking, 1: Room} */
    private function roomServiceStay(Restaurant $restaurant, ?User $customer, array $data): array
    {
        $booking = $this->roomServiceStays($restaurant, $customer)->firstWhere('id', (int) ($data['booking_id'] ?? 0));

        if (! $booking) {
            $this->fail('booking_id', 'Room service is for guests checked in at this resort. Choose your stay.');
        }

        // No room given and a single-room stay → that room; a given room must belong to the stay.
        $roomId = (int) ($data['room_id'] ?? 0);
        $room = $roomId === 0 && $booking->rooms->count() === 1
            ? $booking->rooms->first()->room
            : $booking->rooms->pluck('room')->firstWhere('id', $roomId);

        if (! $room) {
            $this->fail('room_id', 'Choose the room to deliver to.');
        }

        return [$booking, $room];
    }

    /** The chosen active zone of this restaurant, covering the drop-off point. */
    private function deliveryZone(Restaurant $restaurant, array $data): DeliveryZone
    {
        $zone = $restaurant->deliveryZones()->active()->find((int) ($data['delivery_zone_id'] ?? 0));

        if (! $zone) {
            $this->fail('delivery_zone_id', 'Choose a delivery area.');
        }

        $lat = isset($data['delivery_lat']) ? (float) $data['delivery_lat'] : null;
        $lng = isset($data['delivery_lng']) ? (float) $data['delivery_lng'] : null;

        if (! $zone->covers($lat, $lng, $restaurant)) {
            $this->fail('delivery_zone_id', $lat === null
                ? $zone->name.' delivers within '.(float) $zone->radius_km.' km — share your location so we can check.'
                : 'That location is outside '.$zone->name.' ('.(float) $zone->radius_km.' km).');
        }

        return $zone;
    }

    /** Scheduled orders: at least the prep time ahead, at most 7 days out. */
    private function scheduledFor(Restaurant $restaurant, ?string $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        $at = CarbonImmutable::parse($value);

        if ($at->lt(now()->addMinutes((int) $restaurant->prep_minutes)) || $at->gt(now()->addDays(7))) {
            $this->fail('scheduled_for', 'Schedule between '.(int) $restaurant->prep_minutes.' minutes and 7 days from now.');
        }

        return $at;
    }

    private function promotionFor(Restaurant $restaurant, ?string $code, float $subtotal): ?Promotion
    {
        if (blank($code)) {
            return null;
        }

        $promotion = Promotion::lookup($code, Promotion::FOR_ORDERS);
        $rejection = $promotion ? ($promotion->couponRejection() ?? $promotion->orderRejectionFor((int) $restaurant->id, $subtotal)) : 'This promo code does not exist.';

        if ($rejection !== null) {
            $this->fail('promo_code', $rejection);
        }

        return $promotion;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
