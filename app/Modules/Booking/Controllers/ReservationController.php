<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Tenant;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Property;
use App\Support\ModuleService;
use Illuminate\Http\Request;

/**
 * Marketplace reservation request (POST /property/{slug}/reserve).
 *
 * Creates a `pending` booking that holds inventory until the host confirms
 * it (PayMongo in Phase 07 will confirm it from a verified payment instead).
 * Only published listings whose business runs the booking module accept
 * reservations; everything else is a 404.
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly ModuleService $modules,
    ) {}

    public function store(Request $request, string $property)
    {
        $listing = Property::publicQuery()->where('slug', $property)->firstOrFail();

        abort_unless(self::acceptsReservations($listing, $this->modules), 404);

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

        $booking = $this->bookings->asTenantOf($listing, fn () => $this->bookings->reserve(
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

        return redirect()->route('account.bookings.show', $booking->reference)
            ->with('success', 'Reservation '.$booking->reference.' sent — the host will confirm it shortly.');
    }

    public static function acceptsReservations(Property $property, ModuleService $modules): bool
    {
        $module = Module::query()->where('slug', 'booking')->first();
        $tenant = Tenant::query()->find($property->tenant_id);

        return $module && $tenant && $tenant->status === 'active' && $modules->isEnabled($module, $tenant);
    }
}
