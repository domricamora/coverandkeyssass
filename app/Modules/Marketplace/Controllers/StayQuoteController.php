<?php

namespace App\Modules\Marketplace\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Controllers\ReservationController;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Services\AvailabilityService;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Live stay quote for the property page's booking panel (JSON): every
 * bookable room type for the dates and party size, with free rooms and the
 * full-stay price, plus an optional promo code. Prices come from
 * BookingService::quote(), the same math reserve() charges.
 */
class StayQuoteController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly AvailabilityService $availability,
    ) {}

    public function __invoke(Request $request, string $property)
    {
        $listing = Property::publicQuery()->where('slug', $property)->firstOrFail();
        abort_unless(ReservationController::acceptsReservations($listing, app(ModuleService::class)), 404);

        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $in = CarbonImmutable::parse($validated['check_in'])->startOfDay();
        $out = CarbonImmutable::parse($validated['check_out'])->startOfDay();
        $nights = (int) $in->diffInDays($out);
        $guests = (int) ($validated['guests'] ?? 1);

        return app(TenantContext::class)->runAs($listing, function () use ($listing, $in, $out, $nights, $guests, $validated) {
            $options = [];

            foreach ($listing->roomTypes()->active()->sorted()->get() as $type) {
                $option = ['room_type_id' => $type->id, 'name' => $type->name, 'sleeps' => (int) $type->max_guests, 'fits' => $type->max_guests >= $guests];
                $option['free'] = count($this->availability->freeRoomIds($type, $in, $out->subDay()));

                try {
                    $total = $this->bookings->quote($type, $in, $out)['total'];
                    $option += ['total' => $total, 'per_night' => round($total / $nights, 2), 'note' => null];
                } catch (ValidationException $e) {
                    $option += ['total' => null, 'per_night' => null, 'note' => collect($e->errors())->flatten()->first()];
                }

                $options[] = $option;
            }

            $promo = null;
            $promoError = null;

            if (filled($validated['promo_code'] ?? null)) {
                try {
                    $promotion = $this->bookings->promotionFor($listing, $validated['promo_code'], $in, $nights);
                    $cheapest = collect($options)->where('fits', true)->where('free', '>', 0)->whereNotNull('total')->min('total');
                    $promo = ['code' => $promotion->code, 'label' => $promotion->label(), 'discount' => $cheapest ? round($promotion->discountOn((float) $cheapest), 2) : 0];
                } catch (ValidationException $e) {
                    $promoError = collect($e->errors())->flatten()->first();
                }
            }

            $days = $listing->policies['free_cancellation_days'] ?? null;
            $cancelBy = $days !== null ? $in->subDays((int) $days) : null;

            return response()->json([
                'nights' => $nights,
                'currency' => $listing->currency ?: 'PHP',
                'options' => $options,
                'promo' => $promo,
                'promo_error' => $promoError,
                'free_cancel_until' => $cancelBy && $cancelBy->isFuture() ? $cancelBy->format('M j, Y') : null,
            ]);
        });
    }
}
