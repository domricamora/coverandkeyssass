<?php

namespace App\Modules\Pos\Controllers;

use App\Modules\Booking\Models\Booking;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Pos\Models\PosPayment;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Pos\Services\PosService;
use App\Modules\RestaurantManagement\Controllers\RestaurantManagementController;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Register, tickets, kitchen display and cash sessions for one restaurant (Phase 19). */
class PosController extends RestaurantManagementController
{
    public function __construct(
        private readonly PosService $pos,
        private readonly OrderService $orders,
    ) {}

    public function register(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);

        // The register lives on the React restaurant floor.
        return redirect()->route('floor.index', ['restaurant' => $restaurant->slug]);
    }

    public function openSession(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'pos.use');
        $this->pos->openSession($this->resolveRestaurant($restaurant), (float) $request->validate(['opening_float' => ['required', 'numeric', 'min:0']])['opening_float'], $request->user());

        return back()->with('success', 'Register opened.');
    }

    public function closeSession(Request $request, string $restaurant, string $session)
    {
        $this->authorizeTo($request, 'pos.manage');
        $restaurant = $this->resolveRestaurant($restaurant);
        $validated = $request->validate(['counted_cash' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:500']]);

        $session = $this->pos->closeSession(PosSession::query()->where('restaurant_id', $restaurant->id)->findOrFail($session), (float) $validated['counted_cash'], $request->user(), $validated['notes'] ?? null);

        return redirect()->route('pos.sessions.show', [$restaurant, $session->id])->with('success', 'Day closed. Variance '.\App\Support\Currency::symbol().number_format((float) $session->variance, 2).'.');
    }

    public function showSession(Request $request, string $restaurant, string $session)
    {
        $this->authorizeTo($request, 'pos.manage');
        $restaurant = $this->resolveRestaurant($restaurant);
        $session = PosSession::query()->where('restaurant_id', $restaurant->id)->with(['opener', 'closer'])->findOrFail($session);

        return \Inertia\Inertia::render('RestaurantFloor/ZReport', [
            'restaurant' => $restaurant->name,
            'session' => [
                'opened' => $session->opened_at->format('M j, g:i A').' by '.($session->opener?->name ?? '—'),
                'closed' => $session->closed_at ? $session->closed_at->format('M j, g:i A').' by '.($session->closer?->name ?? '—') : null,
                'float' => (float) $session->opening_float,
                'counted' => $session->closed_at ? (float) $session->counted_cash : null,
                'variance' => $session->closed_at ? (float) $session->variance : null,
                'notes' => $session->notes,
            ],
            'report' => $this->pos->report($session),
            'urls' => ['floor' => route('floor.index', ['restaurant' => $restaurant->slug])],
        ]);
    }

    public function storeTicket(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $validated = $request->validate($this->lineRules() + [
            'restaurant_table_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:160'],
        ]);

        $table = isset($validated['restaurant_table_id']) ? RestaurantTable::query()->findOrFail($validated['restaurant_table_id']) : null;
        $order = $this->orders->placeAtRegister($restaurant, [$this->line($validated)], $table, $request->user(), $validated['customer_name'] ?? null);

        return redirect()->route('pos.tickets.show', [$restaurant, $order->reference])->with('success', 'Ticket '.$order->reference.' sent to the kitchen.');
    }

    public function showTicket(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $order = $this->ticket($restaurant, $ticket);

        return redirect()->route('floor.index', ['restaurant' => $restaurant->slug, 'ticket' => $order->reference]);
    }

    public function addLine(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $this->orders->addLines($this->ticket($restaurant, $ticket), [$this->line($request->validate($this->lineRules()))]);

        return back()->with('success', 'Added.');
    }

    public function discount(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.discount');
        $restaurant = $this->resolveRestaurant($restaurant);
        $validated = $request->validate(['type' => ['required', Rule::in(['percent', 'fixed'])], 'value' => ['required', 'numeric', 'min:0'], 'reason' => ['required', 'string', 'max:160']]);

        $this->orders->applyDiscount($this->ticket($restaurant, $ticket), $validated['type'], (float) $validated['value'], $validated['reason']);

        return back()->with('success', 'Discount applied.');
    }

    public function pay(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $validated = $request->validate([
            'method' => ['required', Rule::in(PosPayment::METHODS)],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'required_unless:method,cash'],
            'tendered' => ['nullable', 'numeric', 'min:0.01', 'required_if:method,cash'],
            'reference' => ['nullable', 'string', 'max:120', 'required_if:method,gift_card'],
        ]);

        $payment = $this->pos->pay($this->ticket($restaurant, $ticket), $validated['method'], (float) ($validated['amount'] ?? $validated['tendered']), $request->user(), isset($validated['tendered']) ? (float) $validated['tendered'] : null, $validated['reference'] ?? null);

        return back()->with('success', 'Payment taken'.($payment->change_given > 0 ? ' — change '.\App\Support\Currency::symbol().number_format((float) $payment->change_given, 2) : '').'.');
    }

    public function chargeToRoom(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        [$bookingId, $roomId] = array_map('intval', explode(':', $request->validate(['room_stay' => ['required', 'regex:/^\d+:\d+$/']])['room_stay']));

        $this->pos->chargeToRoom($this->ticket($restaurant, $ticket), Booking::query()->findOrFail($bookingId), $roomId, $request->user());

        return back()->with('success', 'Charged to the room folio.');
    }

    public function close(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $this->pos->close($this->ticket($restaurant, $ticket));

        return redirect()->route('floor.index', ['restaurant' => $restaurant->slug])->with('success', 'Ticket closed.');
    }

    public function cancel(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $order = $this->ticket($restaurant, $ticket);

        if ($order->payment_status !== Order::UNPAID || PosPayment::query()->where('order_id', $order->id)->exists()) {
            return back()->withErrors(['order' => 'A ticket with payments cannot be cancelled — refund it once closed.']);
        }

        $this->orders->transition($order, Order::CANCELLED, $request->input('reason') ?: 'Voided at the register');

        return redirect()->route('floor.index', ['restaurant' => $restaurant->slug])->with('success', 'Ticket voided.');
    }

    public function refund(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.refund');
        $restaurant = $this->resolveRestaurant($restaurant);
        $validated = $request->validate(['method' => ['required', Rule::in(PosPayment::REFUND_METHODS)], 'reason' => ['required', 'string', 'max:160']]);

        $this->pos->refund($this->ticket($restaurant, $ticket), $validated['method'], $validated['reason'], $request->user());

        return back()->with('success', 'Refunded.');
    }

    public function receipt(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $order = $this->ticket($restaurant, $ticket)->load(['items', 'table']);

        return view('pos::receipt', [
            'restaurant' => $restaurant,
            'order' => $order,
            'payments' => PosPayment::query()->where('order_id', $order->id)->oldest('id')->get(),
        ]);
    }

    public function kitchen(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);

        // The kitchen rail is part of the restaurant floor.
        return redirect()->route('floor.index', ['restaurant' => $restaurant->slug]);
    }

    /** Kitchen display bump: accepted → preparing → ready (any channel). */
    public function bump(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $order = Order::query()->where('restaurant_id', $restaurant->id)->where('reference', $ticket)->firstOrFail();

        $next = [Order::ACCEPTED => Order::PREPARING, Order::PREPARING => Order::READY][$order->status] ?? null;
        abort_if($next === null, 422, 'Nothing to bump.');
        $this->orders->transition($order, $next);

        return back();
    }

    // ------------------------------------------------------------------

    private function ticket(Restaurant $restaurant, string $reference): Order
    {
        return Order::query()->where('restaurant_id', $restaurant->id)->where('channel', Order::CHANNEL_POS)->where('reference', $reference)->firstOrFail();
    }

    private function lineRules(): array
    {
        return [
            'item_id' => ['required', 'integer'],
            'options' => ['nullable', 'array'],
            'options.*' => ['integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.OrderService::MAX_QUANTITY],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function line(array $validated): array
    {
        return ['item_id' => $validated['item_id'], 'option_ids' => $validated['options'] ?? [], 'quantity' => $validated['quantity'], 'notes' => $validated['notes'] ?? null];
    }
}
