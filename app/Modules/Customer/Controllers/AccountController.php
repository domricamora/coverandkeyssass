<?php

namespace App\Modules\Customer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Marketplace\Models\Favorite;
use App\Modules\Marketplace\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Customer portal (Phase 06): trips, invoices, cancellations, reviews and
 * notifications for the signed-in guest, across every business they booked.
 *
 * Bookings are tenant-owned rows read without a tenant session, so the
 * tenant scope is lifted in exactly one place (Booking::forCustomer) and
 * anything that needs tenant relations runs inside BookingService::asTenantOf.
 */
class AccountController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function dashboard(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Account/Home', [
            'name' => $user->name,
            'upcoming' => $this->trips($request, upcoming: true)->limit(5)->get()->map($this->tripRow(...)),
            'past' => $this->trips($request, upcoming: false)->limit(5)->get()->map($this->tripRow(...)),
            'counts' => [
                ['Wish list', Favorite::query()->where('user_id', $user->id)->count(), route('marketplace.favorites.index')],
                ['Reviews written', Review::query()->where('user_id', $user->id)->count(), route('account.reviews')],
                ['Unread notifications', $user->unreadNotifications()->count(), route('account.notifications')],
            ],
            'tabs' => self::nav('account.dashboard'),
            'urls' => ['explore' => route('marketplace.hotels'), 'past' => route('account.bookings.index', ['tab' => 'past'])],
        ]);
    }

    public function bookings(Request $request)
    {
        $upcoming = $request->query('tab', 'upcoming') !== 'past';

        return Inertia::render('Account/Trips', [
            'trips' => $this->trips($request, $upcoming)->paginate(10)->withQueryString()->through($this->tripRow(...)),
            'upcoming' => $upcoming,
            'tabs' => self::nav('account.bookings.index'),
            'urls' => ['upcoming' => route('account.bookings.index'), 'past' => route('account.bookings.index', ['tab' => 'past']), 'explore' => route('marketplace.hotels')],
        ]);
    }

    public function booking(Request $request, string $booking)
    {
        $booking = $this->loadBooking($request, $booking);
        $property = $booking->property;
        $payments = Payment::query()->withoutGlobalScope('tenant')->where('tenant_id', $booking->tenant_id)->where('booking_id', $booking->id)->latest('id')->get();
        $paid = $payments->contains('status', Payment::PAID);

        return Inertia::render('Account/Trip', [
            'trip' => [
                'reference' => $booking->reference,
                'status' => $booking->status,
                'statusLabel' => $booking->statusLabel(),
                'dates' => $booking->check_in->format('D, M j').' – '.$booking->check_out->format('D, M j, Y'),
                'nights' => $booking->nights(),
                'guests' => $booking->adults.' '.Str::plural('adult', $booking->adults).($booking->children ? ', '.$booking->children.' '.Str::plural('child', $booking->children) : ''),
                'rooms' => $booking->rooms->map(fn ($line) => ['id' => $line->id, 'name' => $line->roomType?->name, 'total' => $booking->money($line->total)]),
                'discount' => (float) $booking->discount_total > 0 ? ['label' => 'Discount'.($booking->promotion ? ' ('.$booking->promotion->code.')' : ''), 'amount' => $booking->money($booking->discount_total)] : null,
                'total' => $booking->money($booking->total),
                'property' => $property ? [
                    'name' => $property->name,
                    'url' => $property->status === 'published' ? route('marketplace.properties.show', $property->slug) : null,
                    'cover' => $property->coverMedia()?->url(),
                    'where' => $property->locationLabel() ?: null,
                    'times' => $property->check_in_time ? 'Check-in from '.$property->check_in_time.', check-out by '.$property->check_out_time : null,
                    'policy' => $property->policies['cancellation'] ?? null,
                ] : null,
            ],
            'payments' => $payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'date' => ($p->paid_at ?? $p->created_at)->format('M j, Y H:i'),
                'method' => $p->method ? Str::headline($p->method) : Str::headline($p->provider),
                'status' => $p->status,
                'amount' => $p->currency.' '.number_format((float) $p->amount, 2),
                'failure' => $p->failure_reason,
            ]),
            'providers' => ! $paid && in_array($booking->status, [Booking::PENDING, Booking::HELD], true) ? PaymentService::providers() : [],
            'can' => [
                'cancel' => $booking->guestCancellable(),
                'review' => $booking->reviewable() && ! Review::query()->withTrashed()->where('booking_id', $booking->id)->exists(),
            ],
            'tabs' => self::nav('account.bookings.index'),
            'urls' => [
                'trips' => route('account.bookings.index'),
                'invoice' => route('account.bookings.invoice', $booking->reference),
                'folio' => route('account.folio', $booking->reference),
                'message' => route('account.messages.create', ['booking' => $booking->reference]),
                'cancel' => route('account.bookings.cancel', $booking->reference),
                'review' => route('account.bookings.review', $booking->reference),
                'pay' => route('account.payments.pay', $booking->reference),
            ],
        ]);
    }

    /** Account sub-navigation (React pages); the Blade account pages keep customer::partials.nav. */
    public static function nav(string $current): array
    {
        return collect([
            ['account.dashboard', 'Overview'], ['account.bookings.index', 'Trips'], ['account.reservations.index', 'Tables'],
            ['account.orders.index', 'Orders'], ['account.loyalty', 'Rewards'], ['account.payments.index', 'Payments'],
            ['marketplace.favorites.index', 'Wish list'], ['account.reviews', 'Reviews'], ['account.messages.index', 'Messages'],
            ['account.notifications', 'Notifications'], ['profile.edit', 'Profile'],
        ])->map(fn ($t) => ['label' => $t[1], 'href' => route($t[0]), 'active' => $t[0] === $current])->all();
    }

    private function tripRow(Booking $booking): array
    {
        return [
            'reference' => $booking->reference,
            'property' => $booking->property?->name ?? 'Property',
            'cover' => $booking->property?->coverMedia()?->url(),
            'dates' => $booking->check_in->format('M j').' – '.$booking->check_out->format('M j, Y'),
            'status' => $booking->status,
            'statusLabel' => $booking->statusLabel(),
            'total' => $booking->money($booking->total),
            'href' => route('account.bookings.show', $booking->reference),
        ];
    }

    public function invoice(Request $request, string $booking)
    {
        return view('customer::bookings.invoice', ['booking' => $this->loadBooking($request, $booking)]);
    }

    public function cancel(Request $request, string $booking)
    {
        $booking = $this->findBooking($request, $booking);

        if (! $booking->guestCancellable()) {
            throw ValidationException::withMessages(['booking' => 'This booking can no longer be cancelled online — please contact the property.']);
        }

        $this->bookings->asTenantOf($booking, fn () => $this->bookings->transition($booking, Booking::CANCELLED, 'Cancelled by guest'));

        return back()->with('success', 'Booking '.$booking->reference.' cancelled.');
    }

    public function review(Request $request, string $booking)
    {
        $booking = $this->findBooking($request, $booking);

        abort_unless($booking->reviewable(), 403, 'You can review a stay once you have checked out.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'comment' => ['required', 'string', 'max:3000'],
        ] + collect(['cleanliness', 'location', 'service', 'value', 'amenities'])->mapWithKeys(fn ($c) => ['rating_'.$c => ['nullable', 'integer', 'min:1', 'max:5']])->all());

        // One verified review per stay (Phase 24 ReviewService), with category ratings.
        $this->bookings->asTenantOf($booking, fn () => app(\App\Modules\Reviews\Services\ReviewService::class)->reviewStay($booking, $request->user(), $validated));

        return back()->with('success', 'Thanks — your review is live.');
    }

    public function reviews(Request $request)
    {
        return Inertia::render('Account/Reviews', [
            'reviews' => Review::query()
                ->where('user_id', $request->user()->id)
                ->with(['reviewable' => fn ($q) => $q->withoutGlobalScope('tenant')])
                ->latest()
                ->paginate(10)
                ->through(fn (Review $r) => [
                    'id' => $r->id,
                    'listing' => $r->reviewable?->name ?? 'Listing',
                    'rating' => (int) $r->rating,
                    'status' => Str::headline($r->status),
                    'date' => $r->created_at->format('M j, Y'),
                    'title' => $r->title,
                    'comment' => $r->comment,
                    'reply' => $r->host_response,
                ]),
            'tabs' => self::nav('account.reviews'),
        ]);
    }

    public function notifications(Request $request)
    {
        return Inertia::render('Account/Notifications', [
            'notifications' => $request->user()->notifications()->paginate(20)->through(fn ($n) => [
                'id' => $n->id,
                'message' => $n->data['message'] ?? 'Update',
                'href' => ! empty($n->data['link']) ? route('notifications.open', $n->id)
                    : (! empty($n->data['booking_reference']) ? route('account.bookings.show', $n->data['booking_reference']) : null),
                'when' => $n->created_at->diffForHumans(),
                'unread' => $n->read_at === null,
            ]),
            'unread' => $request->user()->unreadNotifications()->count(),
            'tabs' => self::nav('account.notifications'),
            'urls' => ['read' => route('account.notifications.read'), 'settings' => route('account.notification-settings')],
        ]);
    }

    public function markNotificationsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    private function trips(Request $request, bool $upcoming): Builder
    {
        $active = [Booking::PENDING, Booking::HELD, Booking::CONFIRMED, Booking::CHECKED_IN];

        return Booking::forCustomer($request->user())
            ->with(['property' => fn ($q) => $q->withoutGlobalScope('tenant')->withTrashed()])
            ->when($upcoming,
                fn ($q) => $q->whereIn('status', $active)->where('check_out', '>=', today()->toDateString())->orderBy('check_in'),
                fn ($q) => $q->where(fn ($w) => $w->whereNotIn('status', $active)->orWhere('check_out', '<', today()->toDateString()))->orderByDesc('check_in'),
            );
    }

    private function findBooking(Request $request, string $reference): Booking
    {
        return Booking::forCustomer($request->user())->where('reference', $reference)->firstOrFail();
    }

    private function loadBooking(Request $request, string $reference): Booking
    {
        $booking = $this->findBooking($request, $reference);

        return $this->bookings->asTenantOf($booking, fn () => $booking->load(['property', 'rooms.roomType', 'rooms.room', 'promotion']));
    }
}
