<?php

namespace App\Modules\Folio\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Folio\Models\FolioEntry;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guest folio (Phase 14).
 *
 * sync() mirrors what other modules already know into the ledger:
 * room nights (from booking_rooms.nightly_rates — only nights actually
 * stayed after an early check-out), the booking discount, online booking
 * payments / refunds (Payments) and room-service orders charged to the
 * room (Ordering), voiding orders that were cancelled. Every derived line
 * has a unique source_key per booking, so sync() is idempotent and safe to
 * run on every read. Staff add manual charges, payments and refunds.
 *
 * Balance = charges − payments + refunds (voided lines excluded).
 * Runs inside the booking's tenant.
 */
class FolioService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function sync(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            // Serialise concurrent syncs of one folio.
            Booking::query()->whereKey($booking->id)->lockForUpdate()->value('id');

            $posted = FolioEntry::query()->where('booking_id', $booking->id)->whereNotNull('source_key')->get()->keyBy('source_key');

            $this->syncNights($booking, $posted);
            $this->syncPayments($booking, $posted);
            $this->syncOrders($booking, $posted);
        });
    }

    /** @return array{charges: float, payments: float, refunds: float, balance: float, by_category: array<string, float>} */
    public function totals(Booking $booking): array
    {
        $live = FolioEntry::query()->where('booking_id', $booking->id)->live()->get();
        $charges = $live->where('type', FolioEntry::CHARGE);
        $payments = round((float) $live->where('type', FolioEntry::PAYMENT)->sum('amount'), 2);
        $refunds = round((float) $live->where('type', FolioEntry::REFUND)->sum('amount'), 2);
        $chargeTotal = round((float) $charges->sum('amount'), 2);

        return [
            'charges' => $chargeTotal,
            'payments' => $payments,
            'refunds' => $refunds,
            'balance' => round($chargeTotal - $payments + $refunds, 2),
            'by_category' => $charges->groupBy('category')->map(fn ($rows) => round((float) $rows->sum('amount'), 2))->all(),
        ];
    }

    public function addCharge(Booking $booking, string $category, string $description, float $unitAmount, float $quantity, User $by, ?string $serviceDate = null): FolioEntry
    {
        if (! in_array($category, FolioEntry::MANUAL_CHARGE_CATEGORIES, true)) {
            $this->fail('category', 'Pick a charge category.');
        }

        $this->ensureOpen($booking);

        return $this->postManual($booking, FolioEntry::CHARGE, $category, $description, $unitAmount, $quantity, $by, serviceDate: $serviceDate);
    }

    public function recordPayment(Booking $booking, string $method, float $amount, User $by, ?string $reference = null): FolioEntry
    {
        if (! in_array($method, FolioEntry::PAYMENT_METHODS, true)) {
            $this->fail('method', 'Pick a payment method.');
        }

        return $this->postManual($booking, FolioEntry::PAYMENT, $method, 'Payment ('.$method.')', $amount, 1, $by, $reference);
    }

    /** Money handed back at the desk; never more than was paid (net of earlier refunds). */
    public function recordRefund(Booking $booking, string $method, float $amount, User $by, ?string $reason = null): FolioEntry
    {
        $totals = $this->totals($booking);

        if ($amount > $totals['payments'] - $totals['refunds'] + 0.001) {
            $this->fail('amount', 'You can refund at most ₱'.number_format($totals['payments'] - $totals['refunds'], 2).'.');
        }

        return $this->postManual($booking, FolioEntry::REFUND, $method, 'Refund'.($reason ? ': '.$reason : ''), $amount, 1, $by);
    }

    public function void(FolioEntry $entry, string $reason, User $by): void
    {
        if ($entry->voided_at) {
            $this->fail('entry', 'This line is already void.');
        }

        if (! $entry->isManual()) {
            $this->fail('entry', 'Room nights, online payments and room-service orders follow their own records — change those instead.');
        }

        $entry->forceFill(['voided_at' => now(), 'void_reason' => $reason, 'voided_by' => $by->id])->save();

        $this->audit->log('folio.voided', $entry, null, ['reason' => $reason, 'amount' => $entry->amount]);
    }

    // ------------------------------------------------------------------

    /** @param Collection<string, FolioEntry> $posted */
    private function syncNights(Booking $booking, Collection $posted): void
    {
        if (in_array($booking->status, [Booking::CANCELLED, Booking::NO_SHOW, Booking::REFUNDED], true) && $booking->checked_in_at === null) {
            // Never stayed: no room charges (cancellation fees are a manual charge).
            $posted->filter(fn (FolioEntry $e, string $key) => str_starts_with($key, 'night:') || str_starts_with($key, 'discount:'))
                ->each(fn (FolioEntry $e) => $this->voidDerived($e, 'Booking '.$booking->status.' before the stay'));

            return;
        }

        if ((float) $booking->discount_total > 0 && ! $posted->has('discount:'.$booking->id)) {
            $this->postDerived($booking, 'discount:'.$booking->id, FolioEntry::CHARGE, 'room', 'Promo discount', -(float) $booking->discount_total);
        }

        // After an early check-out only the nights before the departure day count.
        $lastNight = $booking->checked_out_at && $booking->checked_out_at->lt($booking->check_out)
            ? max($booking->check_in->toDateString(), $booking->checked_out_at->copy()->subDay()->toDateString())
            : null;

        foreach ($booking->rooms()->with(['room', 'roomType'])->get() as $bookingRoom) {
            foreach ((array) $bookingRoom->nightly_rates as $night => $rate) {
                $key = 'night:'.$bookingRoom->id.':'.$night;
                $stayed = $lastNight === null || $night <= $lastNight;

                if ($stayed && ! $posted->has($key)) {
                    $this->postDerived($booking, $key, FolioEntry::CHARGE, 'room',
                        'Room '.($bookingRoom->room?->room_number ?? '—').' · '.($bookingRoom->roomType?->name ?? 'Room').' · '.date('M j', strtotime($night)),
                        (float) $rate, $night);
                } elseif (! $stayed && $posted->has($key)) {
                    $this->voidDerived($posted[$key], 'Night not stayed (early check-out)');
                }
            }
        }
    }

    private function syncPayments(Booking $booking, Collection $posted): void
    {
        $payments = Payment::query()->where('booking_id', $booking->id)->whereIn('status', [Payment::PAID, Payment::REFUNDED])->get();

        foreach ($payments as $payment) {
            if (! $posted->has('payment:'.$payment->id)) {
                $this->postDerived($booking, 'payment:'.$payment->id, FolioEntry::PAYMENT, 'online', 'Online payment (PayMongo'.($payment->method ? ', '.$payment->method : '').')', (float) $payment->amount, reference: $payment->provider_payment_id);
            }

            if ($payment->status === Payment::REFUNDED && ! $posted->has('refund:'.$payment->id)) {
                $this->postDerived($booking, 'refund:'.$payment->id, FolioEntry::REFUND, 'online', 'Online refund (PayMongo)', (float) $payment->refunded_amount, reference: $payment->refund_id);
            }
        }
    }

    private function syncOrders(Booking $booking, Collection $posted): void
    {
        $orders = Order::query()->where('booking_id', $booking->id)->where('payment_method', Order::PAY_ROOM)->get();

        foreach ($orders as $order) {
            $key = 'order:'.$order->id;
            $void = in_array($order->status, [Order::CANCELLED, Order::REFUNDED], true);

            if (! $void && ! $posted->has($key)) {
                // Delivered to the room → room service; signed at a restaurant table (POS) → food.
                [$category, $label] = $order->fulfillment === Order::ROOM_SERVICE ? ['room_service', 'Room service'] : ['food', 'Restaurant'];
                $this->postDerived($booking, $key, FolioEntry::CHARGE, $category,
                    $label.' · '.$order->restaurant?->name.' · '.$order->reference, (float) $order->total,
                    $order->created_at->toDateString(), $order->reference);
            } elseif ($void && $posted->has($key)) {
                $this->voidDerived($posted[$key], 'Order '.$order->reference.' '.$order->status);
            }
        }
    }

    private function postDerived(Booking $booking, string $key, string $type, string $category, string $description, float $amount, ?string $serviceDate = null, ?string $reference = null): void
    {
        FolioEntry::create([
            'booking_id' => $booking->id,
            'type' => $type,
            'category' => $category,
            'description' => $description,
            'quantity' => 1,
            'unit_amount' => $amount,
            'amount' => $amount,
            'service_date' => $serviceDate,
            'reference' => $reference,
            'source_key' => $key,
        ]);
    }

    private function voidDerived(FolioEntry $entry, string $reason): void
    {
        if (! $entry->voided_at) {
            $entry->forceFill(['voided_at' => now(), 'void_reason' => $reason])->save();
        }
    }

    private function postManual(Booking $booking, string $type, string $category, string $description, float $unitAmount, float $quantity, User $by, ?string $reference = null, ?string $serviceDate = null): FolioEntry
    {
        if ($unitAmount <= 0 || $quantity <= 0) {
            $this->fail('amount', 'Enter an amount above zero.');
        }

        $entry = FolioEntry::create([
            'booking_id' => $booking->id,
            'type' => $type,
            'category' => $category,
            'description' => $description,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'amount' => round($unitAmount * $quantity, 2),
            'service_date' => $serviceDate ?? today()->toDateString(),
            'reference' => $reference,
            'posted_by' => $by->id,
        ]);

        $this->audit->log('folio.'.$type, $entry, null, ['booking' => $booking->reference, 'category' => $category, 'amount' => $entry->amount]);

        return $entry;
    }

    private function ensureOpen(Booking $booking): void
    {
        if (in_array($booking->status, [Booking::COMPLETED, Booking::REFUNDED], true)) {
            $this->fail('booking', 'This folio is closed.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
