<?php

namespace App\Modules\RestaurantManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Tenant;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\RestaurantManagement\Models\TableReservation;
use App\Modules\RestaurantManagement\Services\ReservationService;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Guest side of table reservations (Phase 10): the marketplace request
 * (POST /restaurant/{slug}/reserve → pending) and the customer's own list
 * with self-cancel. Customer reads go through TableReservation::forCustomer().
 */
class GuestReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ModuleService $modules,
    ) {}

    public function store(Request $request, string $restaurant)
    {
        $listing = Restaurant::publicQuery()->where('slug', $restaurant)->firstOrFail();

        abort_unless(self::acceptsReservations($listing, $this->modules), 404);

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:50'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $reservation = $this->reservations->asTenantOf($listing, fn () => $this->reservations->reserve(
            $listing,
            $validated + ['guest_name' => $user->name, 'guest_email' => $user->email],
            TableReservation::SOURCE_MARKETPLACE,
            customer: $user,
            actor: $user,
        ));

        return redirect()->route('account.reservations.index')
            ->with('success', 'Reservation '.$reservation->reference.' requested — the restaurant will confirm it shortly.');
    }

    /** Bookable times for a date, for the marketplace form (JSON). */
    public function slots(Request $request, string $restaurant)
    {
        $listing = Restaurant::publicQuery()->where('slug', $restaurant)->firstOrFail();
        $date = rescue(fn () => CarbonImmutable::parse((string) $request->query('date')), today()->toImmutable(), false);

        return response()->json(['date' => $date->toDateString(), 'slots' => $this->reservations->slotsFor($listing, $date)]);
    }

    public function index(Request $request)
    {
        return view('restaurant-management::account.reservations', [
            'reservations' => TableReservation::forCustomer($request->user())
                ->with('restaurant')
                ->orderByDesc('reserved_at')
                ->paginate(15),
        ]);
    }

    public function cancel(Request $request, string $reservation)
    {
        $reservation = TableReservation::forCustomer($request->user())->where('reference', $reservation)->firstOrFail();

        if (! $reservation->guestCancellable()) {
            throw ValidationException::withMessages(['reservation' => 'This reservation can no longer be cancelled online — please call the restaurant.']);
        }

        $this->reservations->asTenantOf($reservation, fn () => $this->reservations->transition($reservation, TableReservation::CANCELLED, 'Cancelled by guest'));

        return back()->with('success', 'Reservation '.$reservation->reference.' cancelled.');
    }

    /** Review a table visit (Phase 24). */
    public function review(Request $request, string $reservation)
    {
        $reservation = TableReservation::forCustomer($request->user())->where('reference', $reservation)->firstOrFail();
        $validated = $request->validate(['rating' => ['required', 'integer', 'min:1', 'max:5'], 'comment' => ['required', 'string', 'max:3000']]);

        $this->reservations->asTenantOf($reservation, fn () => app(\App\Modules\Reviews\Services\ReviewService::class)->reviewVisit($reservation, $request->user(), $validated));

        return back()->with('success', 'Thanks for your review!');
    }

    public static function acceptsReservations(Restaurant $restaurant, ModuleService $modules): bool
    {
        if (! $restaurant->reservations_enabled) {
            return false;
        }

        $module = Module::query()->where('slug', 'restaurant')->first();
        $tenant = Tenant::query()->find($restaurant->tenant_id);

        return $module && $tenant && $tenant->status === 'active' && $modules->isEnabled($module, $tenant);
    }
}
