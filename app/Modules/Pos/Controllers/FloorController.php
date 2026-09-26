<?php

namespace App\Modules\Pos\Controllers;

use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Pos\Models\PosPayment;
use App\Modules\Pos\Services\PosService;
use App\Modules\RestaurantManagement\Controllers\RestaurantManagementController;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Modules\RestaurantManagement\Models\TableReservation;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Restaurant floor (React/Inertia): the live table plan, today's book, the
 * kitchen rail and a ticket drawer with a fast menu (modifiers included),
 * payments and close-out. Payments, lines, discounts and kitchen bumps use
 * the existing POS endpoints; only opening and closing a ticket from the
 * floor are here, because they return to the floor instead of the register.
 */
class FloorController extends RestaurantManagementController
{
    public function __construct(
        private readonly PosService $pos,
        private readonly OrderService $orders,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'pos.use');

        $restaurants = Restaurant::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $restaurant = $request->filled('restaurant') ? $this->resolveRestaurant((string) $request->query('restaurant')) : $restaurants->first();

        if (! $restaurant) {
            return Inertia::render('RestaurantFloor/Index', ['restaurants' => [], 'restaurant' => null]);
        }

        $open = Order::query()->where('restaurant_id', $restaurant->id)->where('channel', Order::CHANNEL_POS)
            ->whereIn('status', [Order::ACCEPTED, Order::PREPARING, Order::READY])->get();
        $byTable = $open->whereNotNull('restaurant_table_id')->keyBy('restaurant_table_id');

        $reservations = TableReservation::query()->where('restaurant_id', $restaurant->id)
            ->whereDate('reserved_at', today())
            ->whereIn('status', [TableReservation::PENDING, TableReservation::CONFIRMED, TableReservation::SEATED])
            ->orderBy('reserved_at')->get();

        $upcoming = $reservations->filter(fn ($r) => $r->status !== TableReservation::SEATED && $r->restaurant_table_id && $r->reserved_at->between(now()->subMinutes(30), now()->addHours(2)))->keyBy('restaurant_table_id');

        $tables = $restaurant->tables()->active()->with('area:id,name')->orderBy('label')->get();

        return Inertia::render('RestaurantFloor/Index', [
            'restaurants' => $restaurants->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name]),
            'restaurant' => ['slug' => $restaurant->slug, 'name' => $restaurant->name, 'currency' => 'PHP'],
            'session' => ($s = $this->pos->currentSession($restaurant)) ? ['id' => $s->id, 'opened_at' => $s->opened_at->format('g:i A')] : null,
            'areas' => $tables->groupBy(fn ($t) => $t->area?->name ?? 'Floor')->map(fn ($group, $name) => [
                'name' => $name,
                'tables' => $group->map(fn (RestaurantTable $t) => [
                    'id' => $t->id,
                    'label' => $t->label,
                    'seats' => $t->seats,
                    'ticket' => ($o = $byTable->get($t->id)) ? [
                        'reference' => $o->reference,
                        'status' => $o->status,
                        'total' => (float) $o->total,
                        'minutes' => (int) $o->created_at->diffInMinutes(now()),
                        'paid' => $o->payment_status === Order::PAID,
                    ] : null,
                    'reservation' => ($r = $upcoming->get($t->id)) ? ['time' => $r->reserved_at->format('g:i A'), 'name' => $r->guest_name, 'party' => $r->party_size] : null,
                ])->values(),
            ])->values(),
            'counter' => $open->whereNull('restaurant_table_id')->map(fn ($o) => ['reference' => $o->reference, 'name' => $o->customer_name, 'total' => (float) $o->total, 'status' => $o->status])->values(),
            'kitchen' => Order::query()->where('restaurant_id', $restaurant->id)->whereIn('status', [Order::ACCEPTED, Order::PREPARING])
                ->with(['items', 'table:id,label'])->orderByRaw('COALESCE(scheduled_for, accepted_at, created_at)')->limit(24)->get()
                ->map(fn (Order $o) => [
                    'reference' => $o->reference,
                    'status' => $o->status,
                    'where' => $o->table?->label ? 'Table '.$o->table->label : ucfirst(str_replace('_', ' ', (string) $o->fulfillment)),
                    'minutes' => (int) ($o->accepted_at ?? $o->created_at)->diffInMinutes(now()),
                    'items' => $o->items->map(fn (OrderItem $i) => ['qty' => $i->quantity, 'name' => $i->name, 'mods' => collect($i->modifiers)->pluck('name')->join(', '), 'notes' => $i->notes]),
                    'bump' => route('pos.kitchen.bump', [$restaurant->slug, $o->reference]),
                ]),
            'book' => $reservations->map(fn (TableReservation $r) => [
                'reference' => $r->reference,
                'time' => $r->reserved_at->format('g:i A'),
                'name' => $r->guest_name,
                'party' => $r->party_size,
                'status' => $r->status,
                'table' => $r->restaurant_table_id,
                'note' => $r->special_requests,
                'transition' => route('restaurants.reservations.transition', [$restaurant->slug, $r->reference]),
            ]),
            'menu' => $restaurant->menuCategories()->where('is_active', true)->orderBy('sort_order')->with(['items' => fn ($q) => $q->where('is_available', true)->orderBy('name')->with('modifierGroups.options')])->get()
                ->map(fn ($c) => [
                    'name' => $c->name,
                    'items' => $c->items->map(fn ($i) => [
                        'id' => $i->id,
                        'name' => $i->name,
                        'price' => (float) $i->price,
                        'groups' => $i->modifierGroups->map(fn ($g) => [
                            'id' => $g->id, 'name' => $g->name, 'min' => (int) $g->min_select, 'max' => $g->max_select,
                            'options' => $g->options->where('is_available', true)->values()->map(fn ($op) => ['id' => $op->id, 'name' => $op->name, 'price' => (float) $op->price]),
                        ]),
                    ]),
                ])->filter(fn ($c) => $c['items']->isNotEmpty())->values(),
            'stats' => [
                'open' => $open->count(),
                'sales' => round((float) PosPayment::query()->whereHas('order', fn ($q) => $q->where('restaurant_id', $restaurant->id))->whereDate('created_at', today())->sum('amount'), 2),
                'covers' => (int) TableReservation::query()->where('restaurant_id', $restaurant->id)->whereDate('reserved_at', today())->whereIn('status', [TableReservation::SEATED, TableReservation::COMPLETED])->sum('party_size'),
                'booked' => $reservations->where('status', '!=', TableReservation::SEATED)->count(),
            ],
            'urls' => [
                'self' => route('floor.index'),
                'open' => route('floor.tickets.store', $restaurant->slug),
                'session' => route('pos.sessions.open', $restaurant->slug),
                'register' => route('pos.register', $restaurant->slug),
            ],
            'can' => ['discount' => $request->user()->hasPermissionTo('pos.discount'), 'book' => $request->user()->hasPermissionTo('reservations.manage')],
            'methods' => array_values(array_diff(PosPayment::METHODS, ['gift_card'])),
            'ticket' => fn () => $request->filled('ticket') ? $this->ticketDetail($restaurant, (string) $request->query('ticket')) : null,
        ]);
    }

    /** Open a ticket from the floor (seat a table / counter order) with its first lines. */
    public function store(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);

        $validated = $request->validate([
            'restaurant_table_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'lines' => ['required', 'array', 'min:1', 'max:40'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.options' => ['nullable', 'array'],
            'lines.*.options.*' => ['integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.OrderService::MAX_QUANTITY],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $table = isset($validated['restaurant_table_id']) ? RestaurantTable::query()->where('restaurant_id', $restaurant->id)->findOrFail($validated['restaurant_table_id']) : null;
        $lines = array_map(fn ($l) => ['item_id' => $l['item_id'], 'option_ids' => $l['options'] ?? [], 'quantity' => $l['quantity'], 'notes' => $l['notes'] ?? null], $validated['lines']);

        $order = $this->orders->placeAtRegister($restaurant, [array_shift($lines)], $table, $request->user(), $validated['customer_name'] ?? null);

        if ($lines !== []) {
            $order = $this->orders->addLines($order, $lines);
        }

        return redirect()->route('floor.index', ['restaurant' => $restaurant->slug, 'ticket' => $order->reference])
            ->with('success', ($table ? 'Table '.$table->label : 'Order').' sent to the kitchen.');
    }

    public function close(Request $request, string $restaurant, string $ticket)
    {
        $this->authorizeTo($request, 'pos.use');
        $restaurant = $this->resolveRestaurant($restaurant);
        $order = Order::query()->where('restaurant_id', $restaurant->id)->where('channel', Order::CHANNEL_POS)->where('reference', $ticket)->firstOrFail();

        $this->pos->close($order);

        return redirect()->route('floor.index', ['restaurant' => $restaurant->slug])->with('success', 'Ticket '.$order->reference.' closed. Table is free.');
    }

    private function ticketDetail(Restaurant $restaurant, string $reference): array
    {
        $order = Order::query()->where('restaurant_id', $restaurant->id)->where('channel', Order::CHANNEL_POS)->where('reference', $reference)->with(['items', 'table:id,label'])->firstOrFail();

        return [
            'reference' => $order->reference,
            'status' => $order->status,
            'where' => $order->table?->label ? 'Table '.$order->table->label : ($order->customer_name ?: 'Counter'),
            'items' => $order->items->map(fn (OrderItem $i) => ['id' => $i->id, 'qty' => $i->quantity, 'name' => $i->name, 'mods' => collect($i->modifiers)->pluck('name')->join(', '), 'notes' => $i->notes, 'total' => (float) $i->line_total]),
            'subtotal' => (float) $order->subtotal,
            'discount' => (float) $order->discount_total,
            'tax' => (float) $order->tax_total,
            'total' => (float) $order->total,
            'due' => $this->pos->balanceDue($order),
            'paid' => $order->payment_status === Order::PAID,
            'payments' => PosPayment::query()->where('order_id', $order->id)->oldest('id')->get()->map(fn (PosPayment $p) => ['method' => $p->method, 'amount' => (float) $p->amount, 'change' => (float) $p->change_given]),
            'urls' => [
                'lines' => route('pos.tickets.lines', [$restaurant->slug, $order->reference]),
                'pay' => route('pos.tickets.pay', [$restaurant->slug, $order->reference]),
                'discount' => route('pos.tickets.discount', [$restaurant->slug, $order->reference]),
                'close' => route('floor.tickets.close', [$restaurant->slug, $order->reference]),
                'receipt' => route('pos.tickets.receipt', [$restaurant->slug, $order->reference]),
            ],
        ];
    }
}
