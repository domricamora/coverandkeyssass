<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Notify\Models\NotificationPreference;
use App\Modules\Notify\Models\PushMessage;
use App\Modules\Notify\Notifications\OrderReceived;
use App\Modules\Notify\Notifications\PaymentFailed;
use App\Modules\Notify\Notifications\PaymentReceived;
use App\Modules\Notify\Notifications\TrialExpiring;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\Marketing\Support\SmsSender;
use App\Support\ModuleService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PayMongoFake;

/*
| Phase 26 (Notifications) — channel defaults and per-user preferences, SMS
| and the push outbox, payment / order / trial notifications, the staff
| notification centre, the settings page and push device registration.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    PayMongoFake::configure();
    SmsSender::$sent = [];
});

function paidReceipt(): array
{
    [$guest, $booking] = PayMongoFake::pendingPaidFlow();
    PayMongoFake::fake(paid: true);
    PayMongoFake::webhook('evt_paid', 'checkout_session.payment.paid', ['id' => 'cs_test_1'])->assertOk();

    return [$guest, $booking];
}

it('sends the payment receipt in-app and by email by default', function () {
    Notification::fake();
    [$guest, $booking] = paidReceipt();

    Notification::assertSentTo($guest, PaymentReceived::class, function ($notification, array $channels) use ($booking, $guest) {
        $payload = $notification->toArray($guest);

        return $channels === ['database', 'mail']
            && $payload['reference'] === $booking->reference
            && $payload['link'] === route('account.bookings.show', $booking->reference);
    });
});

it('follows preferences: opt out of email, opt in to SMS and push with a device', function () {
    $guest = User::factory()->create(['phone' => '09171234567']);
    NotificationPreference::create(['user_id' => $guest->id, 'event' => 'payment_received', 'channel' => 'mail', 'enabled' => false]);
    NotificationPreference::create(['user_id' => $guest->id, 'event' => 'payment_received', 'channel' => 'sms', 'enabled' => true]);
    NotificationPreference::create(['user_id' => $guest->id, 'event' => 'payment_received', 'channel' => 'push', 'enabled' => true]);

    $this->actingAs($guest)->postJson(route('account.push-devices.store'), ['token' => 'tok-1', 'platform' => 'android'])->assertOk();

    $payment = new Payment(['amount' => 500, 'currency' => 'PHP']);
    $payment->id = 1;
    $notification = new PaymentReceived($payment, 'BK-1', route('account.dashboard'));

    expect($notification->via($guest))->toBe(['database', \App\Modules\Notify\Channels\SmsChannel::class, \App\Modules\Notify\Channels\PushChannel::class]);

    // notifyNow: this test is about channel preferences; the Payment above is an
    // unsaved stand-in, which a queued notification could not re-fetch by id.
    $guest->notifyNow($notification);

    expect(SmsSender::$sent)->toHaveCount(1)
        ->and(SmsSender::$sent[0]['to'])->toBe('09171234567')
        ->and(SmsSender::$sent[0]['text'])->toContain('₱500.00 for BK-1')
        ->and(PushMessage::query()->sole())
        ->user_id->toBe($guest->id)
        ->event->toBe('payment_received')
        ->sent_at->toBeNull()
        ->and($guest->notifications()->count())->toBe(1);

    // No device → no push even when enabled.
    $this->deleteJson(route('account.push-devices.destroy'), ['token' => 'tok-1'])->assertOk();
    expect($notification->via($guest->refresh()))->not->toContain(\App\Modules\Notify\Channels\PushChannel::class);
});

it('tells the guest when a payment fails', function () {
    [$guest, $booking] = PayMongoFake::pendingPaidFlow();
    $payment = Payment::forCustomer($guest)->sole();

    app(PaymentService::class)->markFailed($payment, 'Card declined');
    app(PaymentService::class)->markFailed($payment->refresh(), 'Again'); // not pending any more: no second one

    $sent = $guest->notifications()->where('type', PaymentFailed::class)->get();
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->data['message'])->toContain('Card declined')
        ->and($sent[0]->data['link'])->toBe(route('account.bookings.show', $booking->reference));
});

it('alerts restaurant staff with order access about new online orders', function () {
    [$owner, $tenant] = MarketplaceFixtures::business('Kitchen A');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'restaurant')->firstOrFail(), $tenant);
    $housekeeper = MarketplaceFixtures::member($tenant, 'staff');
    $restaurant = MarketplaceFixtures::restaurant($tenant, $owner, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true]);
    MarketplaceFixtures::asListing($restaurant);
    $category = MenuCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Mains']);
    $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id, 'name' => 'Adobo', 'price' => 180]);
    MarketplaceFixtures::asTenant(null);

    $order = app(TenantContext::class)->runAs($restaurant, fn () => app(OrderService::class)->place($restaurant, [['item_id' => $item->id, 'quantity' => 2]], [
        'fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => 'Ana', 'customer_phone' => '0917',
    ], User::factory()->create()));

    $alert = $owner->notifications()->where('type', OrderReceived::class)->sole();
    expect($alert->data['order_reference'])->toBe($order->reference)
        ->and($alert->data['link'])->toBe(route('restaurants.orders.show', [$restaurant->id, $order->reference]))
        ->and($housekeeper->notifications()->where('type', OrderReceived::class)->count())->toBe(0);

    // The staff notification centre lists it and opening follows the link.
    $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertSee($order->reference);
    $this->get(route('notifications.open', $alert->id))->assertRedirect($alert->data['link']);
    expect($alert->refresh()->read_at)->not->toBeNull();

    // Someone else's notification id is a 404.
    $this->actingAs($housekeeper)->get(route('notifications.open', $alert->id))->assertNotFound();
});

it('warns owners about ending trials once a day', function () {
    [$owner, $tenant] = BookingFixtures::hotel();
    $manager = MarketplaceFixtures::member($tenant, 'manager');
    $subscription = \App\Models\TenantModule::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $subscription->forceFill(['trial_ends_at' => now()->addDays(2)])->save();
    MarketplaceFixtures::asTenant(null); // cron has no tenant context

    $this->artisan('notifications:trials')->assertSuccessful();
    $this->artisan('notifications:trials')->assertSuccessful();

    expect($owner->notifications()->where('type', TrialExpiring::class)->count())->toBe(1)
        ->and($owner->notifications()->first()->data['message'])->toContain('trial for '.$tenant->name)
        ->and($manager->notifications()->where('type', TrialExpiring::class)->count())->toBe(0);

    $subscription->forceFill(['trial_ends_at' => now()->addDays(10)])->save();
    $owner->notifications()->delete();
    $this->artisan('notifications:trials')->assertSuccessful();
    expect($owner->notifications()->count())->toBe(0);
});

it('saves notification settings per event and channel, staff events only for business members', function () {
    $guest = User::factory()->create();

    $this->actingAs($guest)->get(route('account.notification-settings'))
        ->assertOk()->assertSee('Payment received')->assertDontSee('Low stock');

    $this->put(route('account.notification-settings.update'), ['channels' => ['payment_received' => ['sms' => '1']]])->assertRedirect();

    $prefs = NotificationPreference::query()->where('user_id', $guest->id)->where('event', 'payment_received')->pluck('enabled', 'channel');
    expect($prefs->all())->toEqual(['mail' => false, 'sms' => true, 'push' => false])
        ->and(NotificationPreference::query()->where('user_id', $guest->id)->where('event', 'low_stock')->exists())->toBeFalse();

    [$owner] = MarketplaceFixtures::business('Hotel B');
    $this->actingAs($owner)->get(route('account.notification-settings'))->assertOk()->assertSee('Low stock');

    $this->postJson(route('account.push-devices.store'), ['token' => 'x', 'platform' => 'fax'])->assertUnprocessable();
});
