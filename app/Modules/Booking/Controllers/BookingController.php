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
use Illuminate\Validation\Rule;

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

        return view('booking::bookings.index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name', 'slug']),
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

        return view('booking::bookings.create', compact('properties', 'property', 'roomTypes', 'available'));
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

        return view('booking::bookings.show', compact('booking'));
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

    /** Rooms × days grid: who occupies which room each night, plus blocks. */
    public function calendar(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('bookings.view'), 403);

        $properties = Property::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $property = $request->filled('property')
            ? $this->resolveProperty((string) $request->query('property'))
            : $properties->first();

        $start = CarbonImmutable::parse($request->query('start', today()->toDateString()))->startOfDay();
        $days = collect(range(0, 13))->map(fn (int $offset) => $start->addDays($offset));
        $end = $days->last()->toDateString();

        $rooms = $property?->rooms()->with('roomType:id,name')->orderBy('room_type_id')->orderBy('room_number')->get() ?? collect();

        $occupied = DB::table('room_nights')
            ->join('booking_rooms', 'booking_rooms.id', '=', 'room_nights.booking_room_id')
            ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
            ->whereIn('room_nights.room_id', $rooms->pluck('id'))
            ->whereBetween('room_nights.night', [$start->toDateString(), $end])
            ->get(['room_nights.room_id', 'room_nights.night', 'bookings.reference', 'bookings.guest_name', 'bookings.status'])
            ->keyBy(fn ($row) => $row->room_id.'|'.substr((string) $row->night, 0, 10));

        $blocks = $property
            ? AvailabilityBlock::query()->where('property_id', $property->id)->intersecting($start->toDateString(), $end)->get()
            : collect();

        return view('booking::bookings.calendar', compact('properties', 'property', 'days', 'rooms', 'occupied', 'blocks', 'start'));
    }

    private function resolveBooking(string $reference): Booking
    {
        return Booking::query()->where('reference', $reference)->firstOrFail();
    }
}
