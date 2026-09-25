<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Support\Present;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Crm\Models\Contact;
use App\Modules\Delivery\Models\DeliveryZone;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Marketplace\Models\Review;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\PropertyManagement\Models\Room;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Staff surface for the business in X-Tenant (set by ResolveApiTenant).
 * Tenant-owned models are isolated by the BelongsToTenant scope; models
 * without it (reviews, subscriptions) are filtered by tenant id explicitly.
 * Each endpoint checks the permission its dashboard screen checks.
 */
class BusinessController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function users(Request $request): JsonResponse
    {
        $this->can($request, 'team.view');

        $tenant = $this->context->tenant();
        $members = $tenant->tenantUsers()->with('user:id,name,email')->get();

        return response()->json(['data' => $members->map(fn ($m) => [
            'name' => $m->user?->name,
            'email' => $m->user?->email,
            'role' => $m->user?->tenantRole($tenant)?->name,
            'status' => $m->status,
        ])->values()]);
    }

    public function properties(Request $request): JsonResponse
    {
        $this->can($request, 'properties.view');

        return response()->json(Property::query()->with(['propertyType', 'media'])->orderBy('name')->paginate(50)
            ->through(fn ($p) => Present::property($p) + ['status' => $p->status]));
    }

    public function rooms(Request $request): JsonResponse
    {
        $this->can($request, 'rooms.view');

        $rooms = Room::query()->with(['roomType:id,name', 'property:id,slug,name'])
            ->when($request->query('property'), fn ($q, $slug) => $q->whereHas('property', fn ($p) => $p->where('slug', $slug)))
            ->orderBy('property_id')->orderBy('room_number')
            ->paginate(100);

        return response()->json($rooms->through(fn ($r) => [
            'id' => $r->id,
            'number' => $r->room_number,
            'property' => $r->property?->slug,
            'room_type' => $r->roomType?->name,
            'status' => $r->status,
            'housekeeping_status' => $r->housekeeping_status,
        ]));
    }

    public function bookings(Request $request): JsonResponse
    {
        $this->can($request, 'bookings.view');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Booking::statuses())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $bookings = Booking::query()->with('property')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('check_out', '>', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('check_in', '<', $d))
            ->latest('check_in')
            ->paginate(50);

        return response()->json($bookings->through(fn ($b) => Present::booking($b)));
    }

    public function bookingStatus(Request $request, string $reference, BookingService $service): JsonResponse
    {
        $this->can($request, 'bookings.update');
        $data = $request->validate([
            'status' => ['required', Rule::in(Booking::statuses())],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $booking = Booking::query()->where('reference', $reference)->firstOrFail();
        $service->transition($booking, $data['status'], $data['reason'] ?? null);

        return response()->json(['data' => Present::booking($booking->refresh()->load('property'))]);
    }

    public function restaurants(Request $request): JsonResponse
    {
        $this->can($request, 'restaurants.view');

        return response()->json(Restaurant::query()->with(['cuisines', 'media'])->orderBy('name')->paginate(50)
            ->through(fn ($r) => Present::restaurant($r) + ['status' => $r->status]));
    }

    public function orders(Request $request): JsonResponse
    {
        $this->can($request, 'orders.view');
        $filters = $request->validate(['status' => ['nullable', 'string', 'max:30']]);

        $orders = Order::query()->with(['restaurant', 'items'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(50);

        return response()->json($orders->through(fn ($o) => Present::order($o)));
    }

    public function orderStatus(Request $request, string $reference, OrderService $service): JsonResponse
    {
        $this->can($request, 'orders.manage');
        $data = $request->validate([
            'status' => ['required', 'string', 'max:30'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $order = Order::query()->where('reference', $reference)->firstOrFail();
        $service->transition($order, $data['status'], $data['reason'] ?? null);

        return response()->json(['data' => Present::order($order->refresh()->load(['restaurant', 'items']))]);
    }

    public function deliveryZones(Request $request): JsonResponse
    {
        $this->can($request, 'delivery.manage');

        return response()->json(['data' => DeliveryZone::query()->with('restaurant:id,slug')->orderBy('sort_order')->get()->map(fn ($z) => [
            'id' => $z->id,
            'restaurant' => $z->restaurant?->slug,
            'name' => $z->name,
            'radius_km' => $z->radius_km !== null ? (float) $z->radius_km : null,
            'fee' => Present::money($z->fee, 'PHP'),
            'min_order' => Present::money($z->min_order, 'PHP'),
            'free_over' => $z->free_over !== null ? Present::money($z->free_over, 'PHP') : null,
            'eta_minutes' => $z->eta_minutes,
            'active' => (bool) $z->is_active,
        ])->values()]);
    }

    public function payments(Request $request): JsonResponse
    {
        $this->can($request, 'wallet.view');

        return response()->json(Payment::query()->with(['booking', 'order'])->latest()->paginate(50)->through(fn ($p) => Present::payment($p)));
    }

    public function customers(Request $request): JsonResponse
    {
        $this->can($request, 'crm.view');

        $contacts = Contact::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderByDesc('last_activity_at')
            ->paginate(50);

        return response()->json($contacts->through(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'vip' => (bool) $c->is_vip,
            'marketing_consent' => (bool) $c->marketing_consent,
            'stats' => [
                'bookings' => (int) $c->bookings_count,
                'orders' => (int) $c->orders_count,
                'reservations' => (int) $c->reservations_count,
                'total_spend' => Present::money($c->total_spend, 'PHP'),
            ],
            'last_activity_at' => $c->last_activity_at?->toIso8601String(),
        ]));
    }

    public function reviews(Request $request): JsonResponse
    {
        $this->can($request, 'reviews.view');

        $reviews = Review::query()->where('tenant_id', $this->context->id())->with('user')->latest()->paginate(50);

        return response()->json($reviews->through(fn ($r) => Present::review($r) + ['status' => $r->status]));
    }

    public function subscription(Request $request): JsonResponse
    {
        $this->can($request, 'billing.view');

        $subscription = Subscription::query()->where('tenant_id', $this->context->id())->with('items.module')->latest('id')->first();

        return response()->json(['data' => $subscription ? [
            'status' => $subscription->status,
            'interval' => $subscription->billing_interval,
            'current_period' => [
                'start' => $subscription->current_period_start?->toDateString(),
                'end' => $subscription->current_period_end?->toDateString(),
            ],
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
            'modules' => $subscription->items->map(fn ($i) => ['slug' => $i->module?->slug, 'name' => $i->module?->name, 'quantity' => $i->quantity])->values(),
        ] : null]);
    }

    private function can(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403, 'Missing permission: '.$permission);
    }
}
