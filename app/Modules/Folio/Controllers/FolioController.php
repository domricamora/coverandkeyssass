<?php

namespace App\Modules\Folio\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Booking;
use App\Modules\Folio\Models\FolioEntry;
use App\Modules\Folio\Services\FolioService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guest folio screens (Phase 14): the front-desk folio of a booking (sync,
 * manual charges, payments, refunds, voids, print) and the guest's own
 * read-only copy. Bookings are resolved by reference through the tenant
 * scope (host) or Booking::forCustomer() (guest).
 */
class FolioController extends Controller
{
    public function __construct(private readonly FolioService $folio) {}

    public function show(Request $request, string $booking)
    {
        $booking = $this->hostBooking($request, 'folio.view', $booking);
        $this->folio->sync($booking);

        return view('folio::host', $this->data($booking) + ['title' => 'Folio — '.$booking->reference]);
    }

    public function print(Request $request, string $booking)
    {
        $booking = $this->hostBooking($request, 'folio.view', $booking);
        $this->folio->sync($booking);

        return view('folio::print', $this->data($booking));
    }

    public function storeCharge(Request $request, string $booking)
    {
        $booking = $this->hostBooking($request, 'folio.manage', $booking);

        $validated = $request->validate([
            'category' => ['required', Rule::in(FolioEntry::MANUAL_CHARGE_CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'unit_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'quantity' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
            'service_date' => ['nullable', 'date'],
        ]);

        $this->folio->addCharge($booking, $validated['category'], $validated['description'], (float) $validated['unit_amount'], (float) ($validated['quantity'] ?? 1), $request->user(), $validated['service_date'] ?? null);

        return back()->with('success', 'Charge posted.');
    }

    public function storePayment(Request $request, string $booking)
    {
        $booking = $this->hostBooking($request, 'folio.manage', $booking);

        $validated = $request->validate([
            'method' => ['required', Rule::in(FolioEntry::PAYMENT_METHODS)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'reference' => ['nullable', 'string', 'max:120', 'required_if:method,gift_card'],
        ]);

        $this->folio->recordPayment($booking, $validated['method'], (float) $validated['amount'], $request->user(), $validated['reference'] ?? null);

        return back()->with('success', 'Payment recorded.');
    }

    public function storeRefund(Request $request, string $booking)
    {
        $booking = $this->hostBooking($request, 'folio.manage', $booking);

        $validated = $request->validate([
            'method' => ['required', Rule::in(array_diff(FolioEntry::PAYMENT_METHODS, ['gift_card']))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $this->folio->recordRefund($booking, $validated['method'], (float) $validated['amount'], $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Refund recorded.');
    }

    public function void(Request $request, string $booking, string $entry)
    {
        $booking = $this->hostBooking($request, 'folio.void', $booking);
        $entry = FolioEntry::query()->where('booking_id', $booking->id)->findOrFail($entry);

        $this->folio->void($entry, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return back()->with('success', 'Line voided.');
    }

    /** The guest's own folio (read-only). */
    public function guest(Request $request, string $booking)
    {
        $booking = Booking::forCustomer($request->user())->where('reference', $booking)->firstOrFail();

        return app(TenantContext::class)->runAs($booking, function () use ($booking) {
            $this->folio->sync($booking);

            return view('folio::guest', $this->data($booking));
        });
    }

    private function hostBooking(Request $request, string $permission, string $reference): Booking
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);

        return Booking::query()->where('reference', $reference)->firstOrFail();
    }

    private function data(Booking $booking): array
    {
        $booking->loadMissing('property');

        return [
            'booking' => $booking,
            'entries' => FolioEntry::query()->where('booking_id', $booking->id)->with('poster')
                ->orderByRaw("FIELD(type, 'charge', 'payment', 'refund')")->orderBy('service_date')->orderBy('id')->get(),
            'totals' => $this->folio->totals($booking),
        ];
    }
}
