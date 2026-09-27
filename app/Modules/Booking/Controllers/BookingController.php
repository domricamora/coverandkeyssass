<?php

namespace App\Modules\Booking\Controllers;

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Controllers\PropertyManagementController;
use App\Modules\PropertyManagement\Models\AvailabilityBlock;
use App\Modules\PropertyManagement\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Host-side booking desk (Phase 05): list + filters, manual / walk-in
 * reservations, state transitions and the room calendar.
 *
 * Like Property Management, parameters are resolved manually after the
 * tenant context middleware ran — a foreign reference is a plain 404.
 */
class BookingController extends PropertyManagementController
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly AvailabilityService $availability,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('bookings.view'), 403);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Booking::statuses())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $bookings = Booking::query()
            ->with('property:id,name,slug')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('check_out', '>', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('check_in', '<=', $to))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('reference', 'like', '%'.$term.'%')
                ->orWhere('guest_name', 'like', '%'.$term.'%')
                ->orWhere('group_name', 'like', '%'.$term.'%')))
            ->orderByDesc('check_in')
            ->paginate(20)
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings->through(fn (Booking $b) => [
                'reference' => $b->reference,
                'source' => $b->source,
                'guest' => $b->guest_name,
                'group' => $b->group_name,
                'property' => $b->property?->name,
                'stay' => $b->check_in->format('M j').' – '.$b->check_out->format('M j, Y'),
                'status' => $b->status,
                'total' => $b->money($b->total),
                'href' => route('bookings.show', $b->reference),
            ]),
            'filters' => $filters,
            'statuses' => Booking::statuses(),
            'can' => ['create' => $user->hasPermissionTo('bookings.create'), 'promotions' => $user->hasPermissionTo('promotions.manage')],
            'urls' => ['self' => route('bookings.index'), 'create' => route('bookings.create'), 'promotions' => route('bookings.promotions.index'), 'frontdesk' => route('frontdesk.index')],
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('bookings.create'), 403);

        $properties = Property::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $property = $request->filled('property') ? $this->resolveProperty((string) $request->query('property')) : null;
        $roomTypes = $property?->roomTypes()->active()->sorted()->get() ?? collect();

        // Live availability for the chosen dates (informational; reserve()
        // re-checks under lock).
        $available = [];
        $checkIn = $request->query('check_in');
        $checkOut = $request->query('check_out');

        if ($property && $checkIn && $checkOut && strtotime($checkOut) > strtotime($checkIn)) {
            $lastNight = CarbonImmutable::parse($checkOut)->subDay();

            foreach ($roomTypes as $type) {
                $available[$type->id] = $this->availability->availableRoomCount($type, $checkIn, $lastNight);
            }
        }

        return Inertia::render('Bookings/Create', [
            'properties' => $properties->map(fn ($p) => [$p->slug, $p->name]),
            'property' => $property?->slug,
            'dates' => ['check_in' => $checkIn, 'check_out' => $checkOut],
            'roomTypes' => $roomTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'sleeps' => $t->max_guests,
                'rate' => $t->priceLabel(),
                'free' => $available[$t->id] ?? null,
            ])->values(),
            'urls' => ['self' => route('bookings.create'), 'store' => route('bookings.store'), 'index' => route('bookings.index')],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('bookings.create'), 403);

        $validated = $request->validate([
            'property' => ['required', 'string'],
            'source' => ['required', Rule::in([Booking::SOURCE_MANUAL, Booking::SOURCE_WALK_IN])],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.room_type_id' => ['required', 'integer'],
            'rooms.*.quantity' => ['nullable', 'integer', 'min:0', 'max:50'],
            'adults' => ['required', 'integer', 'min:1', 'max:200'],
            'children' => ['nullable', 'integer', 'min:0', 'max:200'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'hold_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        $property = $this->resolveProperty($validated['property']);

        $booking = $this->bookings->reserve($property, $validated, $validated['source'], actor: $request->user());

        return redirect()->route('bookings.show', $booking->reference)
            ->with('success', 'Booking '.$booking->reference.' created — '.strtolower($booking->statusLabel()).'.');
    }

    public function show(Request $request, string $booking)
    {
        abort_unless($request->user()->hasPermissionTo('bookings.view'), 403);

        $booking = $this->resolveBooking($booking)->load(['property', 'rooms.room', 'rooms.roomType', 'promotion', 'customer']);

        $user = $request->user();
        $b = $booking;

        return Inertia::render('Bookings/Show', [
            'booking' => [
                'reference' => $b->reference,
                'status' => $b->status,
                'summary' => $b->property?->name.' · '.$b->check_in->format('D, M j').' – '.$b->check_out->format('D, M j, Y')
                    .' · '.$b->nights().' '.Str::plural('night', $b->nights()).' · '.Str::headline($b->source),
                'hold_expires' => $b->hold_expires_at?->diffForHumans(),
                'guest' => [
                    ['Name', $b->guest_name],
                    ['Email', $b->guest_email],
                    ['Phone', $b->guest_phone],
                    ['Guests', $b->adults.' adults, '.$b->children.' children'],
                    $b->group_name ? ['Group', $b->group_name] : null,
                    $b->special_requests ? ['Special requests', $b->special_requests] : null,
                    $b->cancellation_reason ? ['Cancellation', $b->cancellation_reason] : null,
                ],
                'lines' => $b->rooms->map(fn ($line) => [$line->roomType?->name.' · Room '.$line->room?->room_number, $b->money($line->total)])
                    ->push(['Subtotal', $b->money($b->subtotal)])
                    ->when((float) $b->discount_total > 0, fn ($c) => $c->push(['Discount'.($b->promotion ? ' ('.$b->promotion->code.')' : ''), '− '.$b->money($b->discount_total)])),
                'total' => $b->money($b->total),
                'next' => Booking::TRANSITIONS[$b->status] ?? [],
            ],
            'payments' => Payment::query()->where('booking_id', $b->id)->latest('id')->get()->map(fn (Payment $p) => [
                'id' => $p->id,
                'date' => ($p->paid_at ?? $p->created_at)->format('M j, Y H:i'),
                'method' => $p->method ? Str::headline($p->method) : 'PayMongo',
                'status' => $p->status,
                'note' => $p->failure_reason,
                'amount' => \App\Support\Currency::format($p->amount),
            ]),
            'can' => ['update' => $user->hasPermissionTo('bookings.update'), 'folio' => $user->hasPermissionTo('folio.view')],
            'urls' => [
                'index' => route('bookings.index'),
                'transition' => route('bookings.transition', $b->reference),
                'folio' => route('folio.show', $b->reference),
                'frontdesk' => route('frontdesk.index', ['booking' => $b->reference]),
            ],
        ]);
    }

    public function transition(Request $request, string $booking)
    {
        abort_unless($request->user()->hasPermissionTo('bookings.update'), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Booking::statuses())],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $booking = $this->resolveBooking($booking);

        $this->bookings->transition($booking, $validated['status'], $validated['reason'] ?? null);

        return back()->with('success', 'Booking '.$booking->reference.' is now '.strtolower($booking->statusLabel()).'.');
    }

    /** The old rooms × days grid lives on as the front desk tape chart. */
    public function calendar(Request $request)
    {
        return redirect()->route('frontdesk.index', $request->only('property', 'start'));
    }

    private function resolveBooking(string $reference): Booking
    {
        return Booking::query()->where('reference', $reference)->firstOrFail();
    }
}
