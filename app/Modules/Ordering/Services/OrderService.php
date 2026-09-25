<?php

namespace App\Modules\Ordering\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\Promotion;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Delivery\Models\Driver;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Events\OrderTransitioned;
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
            default => null,
        };

        $zone = $data['fulfillment'] === Order::DELIVERY ? $this->deliveryZone($restaurant, $data) : null;
        $scheduledFor = $this->scheduledFor($restaurant, $data['scheduled_for'] ?? null);

        $order = DB::transaction(function () use ($restaurant, $lines, $data, $customer, $zone, $scheduledFor) {
            $quote = $this->quote($restaurant, $lines, $data['promo_code'] ?? null, $zone);

            if ($zone?->min_order !== null && $quote['subtotal'] < (float) $zone->min_order) {
                $this->fail('delivery_zone_id', 'Delivery to '.$zone->name.' needs an order of at least ₱'.number_format((float) $zone->min_order, 2).'.');
            }

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $customer?->id,
                'promotion_id' => $quote['promotion']?->id,
                'reference' => Order::newReference(),
                'status' => Order::PENDING,
                'fulfillment' => $data['fulfillment'],
                'payment_method' => $data['payment_method'],
                'payment_status' => Order::UNPAID,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'delivery_address' => $zone ? $data['delivery_address'] : null,
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

            $quote['promotion']?->increment('used_count');

            return $order;
        });

        $this->audit->log('order.placed', $order, null, [
            'reference' => $order->reference,
            'total' => $order->total,
            'payment_method' => $order->payment_method,
        ]);

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

        $from = $order->status;

        // Listeners may veto (the PayMongo refund throws when it fails).
        OrderTransitioning::dispatch($order, $to);

        if ($to === Order::OUT_FOR_DELIVERY && ! $order->driver_id) {
            $this->fail('driver_id', 'Assign a driver before dispatching the order.');
        }

        $zoneEta = (int) ($order->zone?->eta_minutes ?? 30);

        $order->status = $to;
        match ($to) {
            // ETA: the scheduled time, or kitchen prep (+ the ride for deliveries).
            Order::ACCEPTED => $order->forceFill([
                'accepted_at' => now(),
                'estimated_at' => $order->scheduled_for ?? now()->addMinutes((int) $order->restaurant->prep_minutes + ($order->fulfillment === Order::DELIVERY ? $zoneEta : 0)),
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

        $promotion = Promotion::query()->where('code', strtoupper(trim($code)))->first();
        $rejection = $promotion ? $promotion->orderRejectionFor((int) $restaurant->id, $subtotal) : 'This promo code does not exist.';

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
