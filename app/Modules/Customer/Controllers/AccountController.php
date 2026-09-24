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

        return view('customer::dashboard', [
            'upcoming' => $this->trips($request, upcoming: true)->limit(5)->get(),
            'past' => $this->trips($request, upcoming: false)->limit(5)->get(),
            'favoritesCount' => Favorite::query()->where('user_id', $user->id)->count(),
            'reviewsCount' => Review::query()->where('user_id', $user->id)->count(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function bookings(Request $request)
    {
        $upcoming = $request->query('tab', 'upcoming') !== 'past';

        return view('customer::bookings.index', [
            'bookings' => $this->trips($request, $upcoming)->paginate(10)->withQueryString(),
            'upcoming' => $upcoming,
        ]);
    }

    public function booking(Request $request, string $booking)
    {
        $booking = $this->loadBooking($request, $booking);

        return view('customer::bookings.show', [
            'booking' => $booking,
            'hasReviewed' => Review::query()->withTrashed()
                ->where('user_id', $request->user()->id)
                ->where('reviewable_type', $booking->property->getMorphClass())
                ->where('reviewable_id', $booking->property_id)
                ->exists(),
        ]);
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
        ]);

        $this->bookings->asTenantOf($booking, function () use ($booking, $request, $validated): void {
            $exists = Review::query()->withTrashed()
                ->where('user_id', $request->user()->id)
                ->where('reviewable_type', $booking->property->getMorphClass())
                ->where('reviewable_id', $booking->property_id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['rating' => 'You have already reviewed this property.']);
            }

            // Verified stay, so the review goes live immediately.
            Review::create($validated + [
                'user_id' => $request->user()->id,
                'reviewable_type' => $booking->property->getMorphClass(),
                'reviewable_id' => $booking->property_id,
                'status' => Review::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
        });

        return back()->with('success', 'Thanks — your review is live.');
    }

    public function reviews(Request $request)
    {
        return view('customer::reviews', [
            'reviews' => Review::query()
                ->where('user_id', $request->user()->id)
                ->with(['reviewable' => fn ($q) => $q->withoutGlobalScope('tenant')])
                ->latest()
                ->paginate(10),
        ]);
    }

    public function notifications(Request $request)
    {
        return view('customer::notifications', [
            'notifications' => $request->user()->notifications()->paginate(20),
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
