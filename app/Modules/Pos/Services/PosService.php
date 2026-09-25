<?php

namespace App\Modules\Pos\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Pos\Models\PosPayment;
use App\Modules\Pos\Models\PosSession;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The register (Phase 19): cash sessions, split payments with change,
 * charge-to-room, closing checks, refunds and the daily Z-report.
 *
 * Money is only taken inside an open session so every peso lands in a
 * drawer count. One open session per restaurant (row-locked open).
 */
class PosService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly AuditLogger $audit,
    ) {}

    public function currentSession(Restaurant $restaurant): ?PosSession
    {
        return PosSession::query()->where('restaurant_id', $restaurant->id)->whereNull('closed_at')->first();
    }

    public function openSession(Restaurant $restaurant, float $float, User $by): PosSession
    {
        return DB::transaction(function () use ($restaurant, $float, $by) {
            Restaurant::query()->whereKey($restaurant->id)->lockForUpdate()->value('id');

            if ($this->currentSession($restaurant)) {
                $this->fail('session', 'The register is already open.');
            }

            $session = PosSession::create(['restaurant_id' => $restaurant->id, 'opened_by' => $by->id, 'opening_float' => max(0, $float), 'opened_at' => now()]);
            $this->audit->log('pos.session_opened', $session, null, ['float' => $session->opening_float]);

            return $session;
        });
    }

    public function balanceDue(Order $order): float
    {
        return round((float) $order->total - (float) PosPayment::query()->where('order_id', $order->id)->sum('amount'), 2);
    }

    /** Take (part of) the bill. Cash may be tendered above the balance — the rest is change. */
    public function pay(Order $order, string $method, float $amount, User $by, ?float $tendered = null, ?string $reference = null): PosPayment
    {
        if (! in_array($method, PosPayment::METHODS, true)) {
            $this->fail('method', 'Pick a payment method.');
        }

        return DB::transaction(function () use ($order, $method, $amount, $by, $tendered, $reference) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $session = $this->mustHaveSession($order);

            if ($order->channel !== Order::CHANNEL_POS || $order->payment_status !== Order::UNPAID || in_array($order->status, [Order::CANCELLED, Order::REFUNDED], true)) {
                $this->fail('amount', 'This ticket is not open for payment.');
            }

            $due = $this->balanceDue($order);
            $tendered = $method === 'cash' ? ($tendered ?? $amount) : null;
            $applied = round($method === 'cash' ? min((float) $tendered, $due) : $amount, 2);

            if ($applied <= 0 || $applied > $due + 0.001) {
                $this->fail('amount', 'Enter an amount up to the balance due (₱'.number_format($due, 2).').');
            }

            $payment = PosPayment::create([
                'order_id' => $order->id,
                'pos_session_id' => $session->id,
                'method' => $method,
                'amount' => $applied,
                'tendered' => $tendered,
                'change_given' => $tendered !== null ? round((float) $tendered - $applied, 2) : null,
                'reference' => $reference,
                'user_id' => $by->id,
            ]);

            if ($this->balanceDue($order) <= 0) {
                $order->forceFill(['payment_status' => Order::PAID])->save();
            }

            return $payment;
        });
    }

    /** Settle the whole ticket on an in-house guest's folio (Phase 14 picks it up). */
    public function chargeToRoom(Order $order, Booking $booking, int $roomId, User $by): Order
    {
        return DB::transaction(function () use ($order, $booking, $roomId, $by) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $session = $this->mustHaveSession($order);

            if ($order->channel !== Order::CHANNEL_POS || $order->payment_status !== Order::UNPAID || $this->balanceDue($order) < (float) $order->total) {
                $this->fail('booking_id', 'Only an unpaid ticket with no payments yet can go on a room.');
            }

            if ($booking->status !== Booking::CHECKED_IN || (int) $booking->tenant_id !== (int) $order->tenant_id) {
                $this->fail('booking_id', 'Pick a guest who is checked in here.');
            }

            if (! $booking->rooms()->where('room_id', $roomId)->exists()) {
                $this->fail('room_id', 'That room is not part of the stay.');
            }

            $order->forceFill([
                'booking_id' => $booking->id,
                'room_id' => $roomId,
                'payment_method' => Order::PAY_ROOM,
                'payment_status' => Order::CHARGED,
                'customer_name' => $booking->guest_name,
            ])->save();

            PosPayment::create(['order_id' => $order->id, 'pos_session_id' => $session->id, 'method' => 'room_charge', 'amount' => $order->total, 'reference' => $booking->reference, 'user_id' => $by->id]);
            $this->audit->log('pos.room_charge', $order, null, ['booking' => $booking->reference, 'amount' => $order->total]);

            return $order;
        });
    }

    /** Close a settled check, stepping the kitchen states the register skipped. */
    public function close(Order $order): Order
    {
        foreach ([Order::ACCEPTED => Order::PREPARING, Order::PREPARING => Order::READY] as $from => $to) {
            if ($order->status === $from) {
                $this->orders->transition($order, $to);
            }
        }

        return $this->orders->transition($order, Order::COMPLETED);
    }

    /** Full refund of a settled register ticket, paid out of the open drawer. */
    public function refund(Order $order, string $method, string $reason, User $by): Order
    {
        if (! in_array($method, PosPayment::METHODS, true)) {
            $this->fail('method', 'Pick how the money goes back.');
        }

        if (! $order->canTransitionTo(Order::REFUNDED)) {
            $this->fail('order', 'Only closed or cancelled register tickets that were paid can be refunded.');
        }

        return DB::transaction(function () use ($order, $method, $reason, $by) {
            $session = $this->mustHaveSession($order);
            $paid = (float) PosPayment::query()->where('order_id', $order->id)->sum('amount');

            PosPayment::create(['order_id' => $order->id, 'pos_session_id' => $session->id, 'method' => $method, 'amount' => -$paid, 'reference' => $reason, 'user_id' => $by->id]);

            return $this->orders->transition($order, Order::REFUNDED, $reason);
        });
    }

    /** Count the drawer and close the day. */
    public function closeSession(PosSession $session, float $counted, User $by, ?string $notes = null): PosSession
    {
        if (! $session->isOpen()) {
            $this->fail('session', 'This session is already closed.');
        }

        $expected = $session->cashInDrawer();

        $session->forceFill([
            'expected_cash' => $expected,
            'counted_cash' => round($counted, 2),
            'variance' => round($counted - $expected, 2),
            'notes' => $notes,
            'closed_by' => $by->id,
            'closed_at' => now(),
        ])->save();

        $this->audit->log('pos.session_closed', $session, null, ['expected' => $expected, 'counted' => $counted, 'variance' => $session->variance]);

        return $session;
    }

    /** Z-report figures for a session. @return array<string, mixed> */
    public function report(PosSession $session): array
    {
        $payments = $session->payments()->get();
        $orderIds = $payments->pluck('order_id')->unique();
        $orders = Order::query()->whereIn('id', $orderIds)->get();

        return [
            'by_method' => $payments->where('amount', '>', 0)->groupBy('method')->map(fn ($rows) => round((float) $rows->sum('amount'), 2))->all(),
            'refunds' => round(abs((float) $payments->where('amount', '<', 0)->sum('amount')), 2),
            'net' => round((float) $payments->sum('amount'), 2),
            'orders' => $orders->count(),
            'discounts' => round((float) $orders->sum('discount_total'), 2),
            'tax' => round((float) $orders->sum('tax_total'), 2),
            'cash_expected' => $session->isOpen() ? $session->cashInDrawer() : (float) $session->expected_cash,
        ];
    }

    private function mustHaveSession(Order $order): PosSession
    {
        $session = PosSession::query()->where('restaurant_id', $order->restaurant_id)->whereNull('closed_at')->first();

        if (! $session) {
            $this->fail('session', 'Open the register first.');
        }

        return $session;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
