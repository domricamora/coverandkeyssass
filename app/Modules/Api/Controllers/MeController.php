<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Support\Present;
use App\Modules\Booking\Controllers\ReservationController;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Messaging\Models\Thread;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * The signed-in customer: their stays, orders, payments, notifications and
 * message threads, plus booking a stay and ordering food. Every query is
 * pinned to the token's user (forCustomer / guest_user_id / notifiable).
 */
class MeController extends Controller
{
    public function bookings(Request $request): JsonResponse
    {
        $bookings = Booking::forCustomer($request->user())
            ->with(['property' => fn ($q) => $q->withoutGlobalScope('tenant')->withTrashed()])
            ->latest('check_in')
            ->paginate(20);

        return response()->json($bookings->through(fn ($b) => Present::booking($b)));
    }

    public function booking(Request $request, string $reference): JsonResponse
    {
        $booking = Booking::forCustomer($request->user())
            ->with(['property' => fn ($q) => $q->withoutGlobalScope('tenant')->withTrashed()])
            ->where('reference', $reference)
            ->firstOrFail();

        return response()->json(['data' => Present::booking($booking)]);
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = Order::forCustomer($request->user())
            ->with(['restaurant' => fn ($q) => $q->withoutGlobalScope('tenant')->withTrashed(), 'items' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->latest()
            ->paginate(20);

        return response()->json($orders->through(fn ($o) => Present::order($o)));
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = Payment::forCustomer($request->user())
            ->with(['booking' => fn ($q) => $q->withoutGlobalScope('tenant'), 'order' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->latest()
            ->paginate(20);

        return response()->json($payments->through(fn ($p) => Present::payment($p)));
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate(30);

        return response()->json($notifications->through(fn ($n) => [
            'id' => $n->id,
            'type' => class_basename($n->type),
            'data' => $n->data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ]));
    }

    public function readNotification(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(null, 204);
    }

    public function messages(Request $request): JsonResponse
    {
        $threads = Thread::query()->where('guest_user_id', $request->user()->id)->with('tenant')->latest('last_message_at')->paginate(20);

        return response()->json($threads->through(fn ($t) => [
            'id' => $t->id,
            'subject' => $t->subject,
            'kind' => $t->kind,
            'status' => $t->status,
            'with' => $t->tenant?->name ?? config('app.name').' support',
            'last_message_at' => $t->last_message_at?->toIso8601String(),
        ]));
    }

    /** Same rules and service call as the website's "Request to book". */
    public function reserve(Request $request, string $slug, BookingService $bookings, ModuleService $modules): JsonResponse
    {
        $listing = Property::publicQuery()->where('slug', $slug)->firstOrFail();
        abort_unless(ReservationController::acceptsReservations($listing, $modules), 404);

        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'room_type_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        $booking = $bookings->asTenantOf($listing, fn () => $bookings->reserve(
            $listing,
            $validated + [
                'rooms' => [['room_type_id' => $validated['room_type_id'], 'quantity' => $validated['quantity']]],
                'guest_name' => $user->name,
                'guest_email' => $user->email,
            ],
            Booking::SOURCE_MARKETPLACE,
            customer: $user,
            actor: $user,
        ));

        return response()->json(['data' => Present::booking($booking->load('property'))], 201);
    }

    /**
     * Pickup or delivery, paid in cash. Online card / e-wallet payment needs
     * the hosted PayMongo checkout, which lives on the website.
     */
    public function order(Request $request, string $slug, OrderService $orders, TenantContext $context): JsonResponse
    {
        $listing = Restaurant::publicQuery()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'lines.*.option_ids' => ['sometimes', 'array', 'max:20'],
            'lines.*.option_ids.*' => ['integer'],
            'fulfillment' => ['required', Rule::in([Order::PICKUP, Order::DELIVERY])],
            'payment_method' => ['required', Rule::in([Order::PAY_CASH])],
            'customer_phone' => ['required', 'string', 'max:40'],
            'delivery_address' => ['required_if:fulfillment,'.Order::DELIVERY, 'nullable', 'string', 'max:255'],
            'delivery_zone_id' => ['required_if:fulfillment,'.Order::DELIVERY, 'nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $order = $context->runAs($listing, fn () => $orders->place(
            $listing,
            $validated['lines'],
            collect($validated)->except('lines')->all() + ['customer_name' => $user->name],
            $user,
        ));

        return response()->json(['data' => Present::order($order->load(['restaurant', 'items']))], 201);
    }
}
