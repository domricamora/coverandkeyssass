<?php

use App\Modules\Api\Support\Present;
use App\Modules\Booking\Models\Booking;
use App\Modules\Ordering\Models\Order;

/*
| Phase 34 (Testing) — unit tests for the rules every flow depends on, with
| no database: the booking and order state machines and the API money shape.
*/

it('allows only the documented booking transitions', function (string $from, array $allowed) {
    $booking = new Booking(['status' => $from]);

    foreach (Booking::statuses() as $to) {
        expect($booking->canTransitionTo($to))->toBe(in_array($to, $allowed, true), "{$from} → {$to}");
    }
})->with([
    'pending' => [Booking::PENDING, [Booking::HELD, Booking::CONFIRMED, Booking::CANCELLED]],
    'confirmed' => [Booking::CONFIRMED, [Booking::CHECKED_IN, Booking::CANCELLED, Booking::NO_SHOW]],
    'checked in' => [Booking::CHECKED_IN, [Booking::CHECKED_OUT]],
    'cancelled' => [Booking::CANCELLED, [Booking::REFUNDED]],
    'refunded is final' => [Booking::REFUNDED, []],
]);

it('keeps every occupying booking status able to leave occupancy', function () {
    // A status that occupies rooms but can never move on would lock inventory forever.
    foreach (Booking::OCCUPYING as $status) {
        expect(Booking::TRANSITIONS[$status])->not->toBeEmpty();
    }
});

it('routes orders through pickup or delivery paths', function () {
    $pickup = new Order(['status' => Order::READY, 'fulfillment' => Order::PICKUP]);
    $delivery = new Order(['status' => Order::READY, 'fulfillment' => Order::DELIVERY]);

    expect($pickup->nextStates())->toBe([Order::COMPLETED])
        ->and($delivery->nextStates())->toBe([Order::OUT_FOR_DELIVERY])
        ->and((new Order(['status' => Order::OUT_FOR_DELIVERY]))->nextStates())->toBe([Order::DELIVERED])
        ->and((new Order(['status' => Order::PREPARING]))->canTransitionTo(Order::COMPLETED))->toBeFalse();
});

it('refunds only paid online or POS orders', function () {
    $paidOnline = new Order(['status' => Order::COMPLETED, 'payment_status' => Order::PAID, 'payment_method' => Order::PAY_ONLINE]);
    $cash = new Order(['status' => Order::COMPLETED, 'payment_status' => Order::PAID, 'payment_method' => Order::PAY_CASH]);
    $unpaid = new Order(['status' => Order::CANCELLED, 'payment_status' => Order::UNPAID, 'payment_method' => Order::PAY_ONLINE]);

    expect($paidOnline->canTransitionTo(Order::REFUNDED))->toBeTrue()
        ->and($cash->canTransitionTo(Order::REFUNDED))->toBeFalse()
        ->and($unpaid->canTransitionTo(Order::REFUNDED))->toBeFalse();
});

it('formats API money as a two-decimal string with a currency', function () {
    expect(Present::money(4800, 'PHP'))->toBe(['amount' => '4800.00', 'currency' => 'PHP'])
        ->and(Present::money('1234.5', null))->toBe(['amount' => '1234.50', 'currency' => 'PHP'])
        ->and(Present::money(0.005, 'USD'))->toBe(['amount' => '0.01', 'currency' => 'USD']);
});
