<?php

namespace App\Modules\Booking\Controllers;

use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\BookingRoom;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Folio\Models\FolioEntry;
use App\Modules\Folio\Services\FolioService;
use App\Modules\Marketplace\Models\Property;
use App\Modules\PropertyManagement\Controllers\PropertyManagementController;
use App\Modules\PropertyManagement\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Front desk (React/Inertia): today's arrivals, departures and in-house
 * guests with one-click check-in/out, a 14-day room tape chart with
 * drag-to-move, and a booking drawer with the folio and quick payments.
 *
 * Actions reuse the existing endpoints (bookings.transition, folio.*);
 * only the room move is new. Everything stays inside the tenant scope.
 */
class FrontDeskController extends PropertyManagementController
{
    private const TAPE_DAYS = 14;

    public function __construct(
        private readonly BookingService $bookings,
        private readonly FolioService $folio,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo('bookings.view'), 403);

        $properties = Property::query()->orderBy('name')->get(['id', 'name', 'slug']);
        $property = $request->filled('property')
            ? $this->resolveProperty((string) $request->query('property'))
            : $properties->first();

        $today = CarbonImmutable::today();
        $start = $request->filled('start')
            ? CarbonImmutable::parse((string) $request->query('start'))->startOfDay()
            : $today->subDay();

        return Inertia::render('FrontDesk/Index', [
            'today' => $today->toDateString(),
            'start' => $start->toDateString(),
            'days' => self::TAPE_DAYS,
            'properties' => $properties->map(fn (Property $p) => ['slug' => $p->slug, 'name' => $p->name]),
            'property' => $property?->slug,
            'can' => [
                'update' => $user->hasPermissionTo('bookings.update'),
                'create' => $user->hasPermissionTo('bookings.create'),
                'folio' => $user->hasPermissionTo('folio.manage'),
            ],
            'urls' => [
                'self' => route('frontdesk.index'),
                'move' => route('frontdesk.move'),
                'create' => route('bookings.create', array_filter(['property' => $property?->slug])),
                'housekeeping' => route('housekeeping.index'),
            ],
            'paymentMethods' => array_values(array_diff(FolioEntry::PAYMENT_METHODS, ['gift_card'])),
            'chargeCategories' => FolioEntry::MANUAL_CHARGE_CATEGORIES,
            ...($property ? $this->board($property, $today, $start) : ['lists' => null, 'stats' => null, 'rooms' => [], 'stays' => []]),
            'selected' => fn () => $request->filled('booking') ? $this->detail($request, (string) $request->query('booking')) : null,
        ]);
    }

    /** Drag on the tape chart: move a booked room's remaining nights to another room. */
    public function move(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('bookings.update'), 403);

        $validated = $request->validate([
            'booking_room' => ['required', 'integer'],
            'room_id' => ['required', 'integer'],
        ]);

        $bookingRoom = BookingRoom::query()->with('booking')->findOrFail($validated['booking_room']);
        $room = Room::query()->findOrFail($validated['room_id']);

        $this->bookings->moveRoom($bookingRoom, $room);

        return back()->with('success', $bookingRoom->booking->guest_name.' moved to room '.$room->room_number.'.');
    }

    // ------------------------------------------------------------------

    private function board(Property $property, CarbonImmutable $today, CarbonImmutable $start): array
    {
        $date = $today->toDateString();
        $with = ['rooms.room:id,room_number'];

        $arrivals = Booking::query()->with($with)->where('property_id', $property->id)
            ->whereDate('check_in', $date)
            ->whereIn('status', [Booking::PENDING, Booking::HELD, Booking::CONFIRMED, Booking::CHECKED_IN])
            ->orderBy('guest_name')->get();

        $departures = Booking::query()->with($with)->where('property_id', $property->id)
            ->whereDate('check_out', $date)
            ->whereIn('status', [Booking::CHECKED_IN, Booking::CHECKED_OUT])
            ->orderBy('guest_name')->get();

        $inHouse = Booking::query()->with($with)->where('property_id', $property->id)
            ->where('status', Booking::CHECKED_IN)
            ->orderBy('check_out')->get();

        $balances = $this->balances($arrivals->concat($departures)->concat($inHouse)->unique('id'));
        $row = fn (Booking $b) => [
            'reference' => $b->reference,
            'guest' => $b->guest_name,
            'group' => $b->group_name,
            'rooms' => $b->rooms->map(fn (BookingRoom $r) => $r->room?->room_number)->filter()->values(),
            'check_in' => $b->check_in->toDateString(),
            'check_out' => $b->check_out->toDateString(),
            'nights' => $b->nights(),
            'guests' => $b->adults + $b->children,
            'status' => $b->status,
            'source' => $b->source,
            'note' => $b->special_requests,
            'currency' => $b->currency,
            'balance' => $balances[$b->id] ?? (float) $b->total,
            'transition' => route('bookings.transition', $b->reference),
        ];

        $rooms = Room::query()->with('roomType:id,name')->where('property_id', $property->id)
            ->where('status', '!=', Room::STATUS_INACTIVE)
            ->orderBy('room_type_id')->orderBy('room_number')->get();

        $end = $start->addDays(self::TAPE_DAYS - 1)->toDateString();

        // One bar per booked room: its first and last night inside the window.
        $stays = DB::table('room_nights')
            ->join('booking_rooms', 'booking_rooms.id', '=', 'room_nights.booking_room_id')
            ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
            ->whereIn('room_nights.room_id', $rooms->pluck('id'))
            ->whereBetween('room_nights.night', [$start->toDateString(), $end])
            ->groupBy('room_nights.booking_room_id', 'room_nights.room_id', 'bookings.reference', 'bookings.guest_name', 'bookings.status')
            ->selectRaw('room_nights.booking_room_id as id, room_nights.room_id as room, bookings.reference, bookings.guest_name as guest, bookings.status, min(room_nights.night) as first, max(room_nights.night) as last')
            ->get()
            ->map(fn ($s) => [...(array) $s, 'first' => substr((string) $s->first, 0, 10), 'last' => substr((string) $s->last, 0, 10)]);

        $sellable = $rooms->filter(fn (Room $r) => $r->status === Room::STATUS_ACTIVE && $r->housekeeping_status !== Room::HK_OUT_OF_ORDER)->pluck('id');
        $occupied = $stays->filter(fn ($s) => $s['first'] <= $date && $s['last'] >= $date)->pluck('room')->unique()->count();

        return [
            'lists' => [
                'arrivals' => $arrivals->map($row)->values(),
                'departures' => $departures->map($row)->values(),
                'inHouse' => $inHouse->map($row)->values(),
            ],
            'stats' => [
                'arrivals' => $arrivals->count(),
                'arrivalsLeft' => $arrivals->where('status', '!=', Booking::CHECKED_IN)->count(),
                'departures' => $departures->count(),
                'departuresLeft' => $departures->where('status', Booking::CHECKED_IN)->count(),
                'inHouse' => $inHouse->count(),
                'occupancy' => $sellable->count() ? (int) round($occupied / $sellable->count() * 100) : 0,
                'occupied' => $occupied,
                'sellable' => $sellable->count(),
                'dirty' => $rooms->where('housekeeping_status', Room::HK_DIRTY)->count(),
                'outOfOrder' => $rooms->count() - $sellable->count(),
            ],
            'rooms' => $rooms->map(fn (Room $r) => [
                'id' => $r->id,
                'number' => $r->room_number,
                'type' => $r->roomType?->name,
                'hk' => $r->housekeeping_status,
                'sellable' => $sellable->contains($r->id),
            ])->values(),
            'stays' => $stays->values(),
        ];
    }

    /**
     * Folio balance per booking (charges − payments + refunds), after
     * syncing each folio so room nights and orders are posted.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return array<int, float>
     */
    private function balances(Collection $bookings): array
    {
        // ponytail: one sync per listed booking (a few queries each); fine at desk scale, batch it if a property lists hundreds a day.
        $bookings->each(fn (Booking $b) => $this->folio->sync($b));

        return FolioEntry::query()->live()->whereIn('booking_id', $bookings->pluck('id'))
            ->groupBy('booking_id')
            ->selectRaw("booking_id, sum(case when type = 'payment' then -amount else amount end) as balance")
            ->pluck('balance', 'booking_id')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    private function detail(Request $request, string $reference): array
    {
        $booking = Booking::query()->with(['rooms.room:id,room_number', 'rooms.roomType:id,name'])->where('reference', $reference)->firstOrFail();
        $canFolio = $request->user()->hasPermissionTo('folio.view');

        if ($canFolio) {
            $this->folio->sync($booking);
        }

        $next = array_values(array_intersect(Booking::TRANSITIONS[$booking->status] ?? [], [Booking::CONFIRMED, Booking::CHECKED_IN, Booking::CHECKED_OUT, Booking::NO_SHOW, Booking::CANCELLED]));

        return [
            'reference' => $booking->reference,
            'guest' => $booking->guest_name,
            'email' => $booking->guest_email,
            'phone' => $booking->guest_phone,
            'group' => $booking->group_name,
            'adults' => $booking->adults,
            'children' => $booking->children,
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'nights' => $booking->nights(),
            'status' => $booking->status,
            'source' => $booking->source,
            'note' => $booking->special_requests,
            'currency' => $booking->currency,
            'total' => (float) $booking->total,
            'rooms' => $booking->rooms->map(fn (BookingRoom $r) => ['id' => $r->id, 'number' => $r->room?->room_number, 'type' => $r->roomType?->name]),
            'next' => $next,
            'folio' => $canFolio ? [
                'entries' => FolioEntry::query()->where('booking_id', $booking->id)->orderBy('service_date')->orderBy('id')->get()
                    ->map(fn (FolioEntry $e) => [
                        'id' => $e->id,
                        'date' => $e->service_date?->toDateString() ?? $e->created_at->toDateString(),
                        'description' => $e->description,
                        'type' => $e->type,
                        'amount' => (float) $e->amount,
                        'void' => $e->voided_at !== null,
                    ]),
                'totals' => $this->folio->totals($booking),
            ] : null,
            'urls' => [
                'transition' => route('bookings.transition', $booking->reference),
                'payment' => route('folio.payments.store', $booking->reference),
                'charge' => route('folio.charges.store', $booking->reference),
                'folio' => route('folio.show', $booking->reference),
                'print' => route('folio.print', $booking->reference),
                'booking' => route('bookings.show', $booking->reference),
            ],
        ];
    }
}
