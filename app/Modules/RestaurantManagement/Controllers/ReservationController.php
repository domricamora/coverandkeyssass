<?php

namespace App\Modules\RestaurantManagement\Controllers;

use App\Modules\RestaurantManagement\Models\TableReservation;
use App\Modules\RestaurantManagement\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Host reservation desk (Phase 10): a day calendar per table, phone/walk-up
 * reservations, and the confirm / seat / complete / cancel / no-show actions.
 */
class ReservationController extends RestaurantManagementController
{
    public function __construct(private readonly ReservationService $reservations) {}

    public function index(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'reservations.view');
        $restaurant = $this->resolveRestaurant($restaurant);

        $date = rescue(fn () => CarbonImmutable::parse((string) $request->query('date', today()->toDateString()))->startOfDay(), today()->toImmutable(), false);

        $reservations = TableReservation::query()
            ->where('restaurant_id', $restaurant->getKey())
            ->whereBetween('reserved_at', [$date, $date->endOfDay()])
            ->with('table')
            ->orderBy('reserved_at')
            ->get();

        $r = $restaurant;
        $manage = $request->user()->hasPermissionTo('reservations.manage');

        return \Inertia\Inertia::render('Restaurants/Reservations', [
            'restaurant' => ['name' => $r->name],
            'tabs' => $this->tabs($r, 'reservations'),
            'date' => $date->toDateString(),
            'dateLabel' => $date->format('l, M j, Y'),
            'covers' => (int) $reservations->whereIn('status', TableReservation::BLOCKING)->sum('party_size'),
            'reservations' => $reservations->map(fn (TableReservation $x) => [
                'reference' => $x->reference,
                'time' => $x->reserved_at->format('g:i A'),
                'span' => $x->reserved_at->format('g:i').'–'.$x->ends_at->format('g:i A'),
                'name' => $x->guest_name,
                'party' => $x->party_size,
                'table_id' => $x->restaurant_table_id,
                'table' => $x->table?->label,
                'status' => $x->status,
                'source' => $x->source,
                'phone' => $x->guest_phone,
                'note' => $x->special_requests,
                'next' => $manage ? (TableReservation::TRANSITIONS[$x->status] ?? []) : [],
                'transition' => route('restaurants.reservations.transition', [$r, $x->reference]),
            ]),
            'tables' => $r->tables()->active()->get()->map(fn ($t) => ['id' => $t->id, 'label' => $t->label, 'seats' => $t->seats]),
            'slots' => $this->reservations->slotsFor($r, $date),
            'can' => ['manage' => $manage],
            'urls' => ['self' => route('restaurants.reservations', $r), 'store' => route('restaurants.reservations.store', $r)],
        ]);
    }

    public function store(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'reservations.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:100'],
            'guest_name' => ['required', 'string', 'max:160'],
            'guest_email' => ['nullable', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'restaurant_table_id' => ['nullable', 'integer',
                Rule::exists('restaurant_tables', 'id')->where('restaurant_id', $restaurant->getKey())],
        ]);

        $reservation = $this->reservations->reserve($restaurant, $validated, TableReservation::SOURCE_HOST, actor: $request->user());

        return redirect()
            ->route('restaurants.reservations', [$restaurant, 'date' => $reservation->reserved_at->toDateString()])
            ->with('success', 'Reservation '.$reservation->reference.' confirmed at table '.$reservation->table->label.'.');
    }

    public function transition(Request $request, string $restaurant, string $reservation)
    {
        $this->authorizeTo($request, 'reservations.manage');
        $restaurant = $this->resolveRestaurant($restaurant);

        $reservation = TableReservation::query()
            ->where('restaurant_id', $restaurant->getKey())
            ->where('reference', $reservation)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                TableReservation::CONFIRMED, TableReservation::SEATED, TableReservation::COMPLETED,
                TableReservation::CANCELLED, TableReservation::NO_SHOW,
            ])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->reservations->transition($reservation, $validated['status'], $validated['reason'] ?? null);

        return back()->with('success', 'Reservation '.$reservation->reference.' is now '.strtolower($reservation->statusLabel()).'.');
    }
}
