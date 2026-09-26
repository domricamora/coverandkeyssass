<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Services\AvailabilityService;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Stay booking, step 2 of 2 (React): the guest reviews dates, room and
 * price, adds details and an optional promo code, then submits to the
 * existing reservation endpoint. Signed-out guests reach it through
 * /continue after signing in, with the selection kept in the query.
 */
class StayReviewController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly AvailabilityService $availability,
    ) {}

    public function __invoke(Request $request, string $property)
    {
        $listing = Property::publicQuery()->with('location')->where('slug', $property)->firstOrFail();
        abort_unless(ReservationController::acceptsReservations($listing, app(ModuleService::class)), 404);

        $stay = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
            'room_type_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        $stay['guests'] = (int) ($stay['guests'] ?? 2);
        $stay['quantity'] = (int) ($stay['quantity'] ?? 1);

        $in = CarbonImmutable::parse($stay['check_in'])->startOfDay();
        $out = CarbonImmutable::parse($stay['check_out'])->startOfDay();
        $nights = (int) $in->diffInDays($out);
        $back = route('marketplace.properties.show', ['property' => $listing->slug, 'check_in' => $stay['check_in'], 'check_out' => $stay['check_out'], 'guests' => $stay['guests']]);

        $option = app(TenantContext::class)->runAs($listing, function () use ($listing, $stay, $in, $out, $nights) {
            $type = $listing->roomTypes()->active()->findOrFail($stay['room_type_id']);

            if (count($this->availability->freeRoomIds($type, $in, $out->subDay())) < $stay['quantity']) {
                return 'That room just sold out for these dates. Pick another room or dates.';
            }

            try {
                $total = $this->bookings->quote($type, $in, $out)['total'];
            } catch (ValidationException $e) {
                return collect($e->errors())->flatten()->first();
            }

            return ['name' => $type->name, 'sleeps' => (int) $type->max_guests, 'per_night' => round($total / $nights, 2), 'total' => $total * $stay['quantity'], 'currency' => $type->currency ?: ($listing->currency ?: 'PHP')];
        });

        if (is_string($option)) {
            return redirect($back)->with('error', $option);
        }

        $days = $listing->policies['free_cancellation_days'] ?? null;
        $cancelBy = $days !== null ? $in->subDays((int) $days) : null;
        $user = $request->user();

        return Inertia::render('Stay/Review', [
            'property' => [
                'name' => $listing->name,
                'where' => $listing->locationLabel() ?: null,
                'cover' => $listing->coverMedia()?->url(),
                'check_in_time' => $listing->check_in_time,
                'check_out_time' => $listing->check_out_time,
            ],
            'stay' => $stay + ['nights' => $nights, 'from' => $in->format('D, M j, Y'), 'to' => $out->format('D, M j, Y')],
            'option' => $option,
            'cancellation' => $cancelBy && $cancelBy->isFuture() ? 'Free cancellation until '.$cancelBy->format('M j, Y') : ($days === null ? 'Non-refundable once the host confirms' : null),
            'guest' => $user ? ['name' => $user->name, 'email' => $user->email] : null,
            'urls' => [
                'quote' => route('marketplace.properties.quote', $listing->slug),
                'reserve' => route('marketplace.properties.reserve', $listing->slug),
                'back' => $back,
                'home' => url('/'),
                'here' => $request->getPathInfo().'?'.$request->getQueryString(),
            ],
        ]);
    }
}
