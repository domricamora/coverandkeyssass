<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\Promotion;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Crm\Models\Contact;
use App\Modules\Folio\Services\FolioService;
use App\Modules\Loyalty\Models\GiftCard;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyProgram;
use App\Modules\Loyalty\Models\Reward;
use App\Modules\Loyalty\Services\GiftCardService;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Pos\Services\PosService;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 23 (Loyalty) — points on stays and orders (events + sync,
| idempotent, reversed on refund), tiers, referrals, rewards (coupon /
| credit), gift cards and credit at the POS and on folios with accounting,
| guest and host screens, permissions.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 12:00'));
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel(2);
    foreach (['crm', 'pos', 'finance'] as $slug) {
        app(ModuleService::class)->enableForTenant(Module::query()->where('slug', $slug)->firstOrFail(), $this->tenant);
    }
    MarketplaceFixtures::asTenant($this->tenant);
    LoyaltyProgram::create(['enabled' => true, 'pesos_per_point' => 100, 'referral_points' => 200]);

    $this->restaurant = MarketplaceFixtures::restaurant($this->tenant, $this->owner, ['status' => Restaurant::STATUS_PUBLISHED, 'ordering_enabled' => true, 'tax_rate' => 12, 'tax_inclusive' => true]);
    MarketplaceFixtures::asTenant($this->tenant);
    $this->item = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'menu_category_id' => MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Mains'])->id, 'name' => 'Kare-kare', 'price' => 500]);
    $this->guest = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);
});

function loyalty(): LoyaltyService
{
    return app(LoyaltyService::class);
}

function checkedOutStay(User $guest, array $attributes = []): Booking
{
    $booking = BookingFixtures::reserve(test()->property, test()->type, $attributes + ['guest_email' => $guest->email, 'guest_name' => $guest->name], Booking::SOURCE_MANUAL, $guest);
    test()->travelTo(CarbonImmutable::parse($booking->check_in->toDateString().' 14:00'));
    app(BookingService::class)->transition($booking, Booking::CHECKED_IN);
    test()->travelTo(CarbonImmutable::parse($booking->check_out->toDateString().' 11:00'));
    app(BookingService::class)->transition($booking->refresh(), Booking::CHECKED_OUT);

    return $booking->refresh();
}

function completedOrder(?User $guest, int $qty = 1): Order
{
    $orders = app(OrderService::class);
    $order = $orders->place(test()->restaurant->refresh(), [['item_id' => test()->item->id, 'quantity' => $qty]], ['fulfillment' => Order::PICKUP, 'payment_method' => Order::PAY_CASH, 'customer_name' => $guest?->name ?? 'Walk-in', 'customer_phone' => $guest ? '0917' : null], $guest);
    foreach ([Order::ACCEPTED, Order::PREPARING, Order::READY, Order::COMPLETED] as $state) {
        $orders->transition($order, $state);
    }

    return $order;
}

function memberOf(User $user): LoyaltyAccount
{
    return LoyaltyAccount::query()->whereHas('contact', fn ($q) => $q->where('user_id', $user->id))->firstOrFail();
}

it('earns points when a stay checks out and an order completes, once, and gives them back on refund', function () {
    checkedOutStay($this->guest); // ₱12,500 → 125 pts
    $order = completedOrder($this->guest, 3); // ₱1,500 → 15 pts

    loyalty()->sync(); // replay: nothing doubles
    $member = memberOf($this->guest);
    expect($member->points_balance)->toBe(140)->and($member->lifetime_points)->toBe(140)->and($member->tier)->toBe('bronze')
        ->and($member->transactions()->where('type', 'earn')->count())->toBe(2);

    // Walk-ins with no details earn nothing.
    completedOrder(null);
    expect(LoyaltyAccount::query()->count())->toBe(1);

    // A refunded order gives its points back (POS refund path not needed: mark via the order state machine).
    app(PosService::class)->openSession($this->restaurant, 0, $this->owner);
    $order->forceFill(['payment_method' => Order::PAY_POS, 'payment_status' => Order::PAID])->save();
    app(OrderService::class)->transition($order, Order::REFUNDED);
    expect(memberOf($this->guest)->points_balance)->toBe(125);

    // Switched off → no earning.
    LoyaltyProgram::query()->update(['enabled' => false]);
    completedOrder($this->guest);
    expect(memberOf($this->guest)->points_balance)->toBe(125);
});

it('climbs tiers on lifetime points, not on manual adjustments', function () {
    completedOrder($this->guest, 50);
    completedOrder($this->guest, 50); // 2 × ₱25,000 → 500 pts → Silver
    $member = memberOf($this->guest);
    expect($member->tier)->toBe('silver')->and($member->nextTier())->toBe(['Gold', 1500]);

    loyalty()->adjust($member, 5000, 'Goodwill', $this->owner);
    expect($member->refresh()->tier)->toBe('silver')->and($member->points_balance)->toBe(5500)
        ->and(fn () => loyalty()->adjust($member, -99999, 'Oops', $this->owner))->toThrow(ValidationException::class);
});

it('rewards both guests when a referred guest first earns', function () {
    completedOrder($this->guest);
    $maria = memberOf($this->guest);

    $friend = User::factory()->create(['name' => 'Ben Cruz', 'email' => 'ben@example.com']);
    $ben = loyalty()->account(Contact::create(['name' => 'Ben Cruz', 'email' => 'ben@example.com', 'user_id' => $friend->id]));

    expect(fn () => loyalty()->applyReferral($ben, 'NOPE'))->toThrow(ValidationException::class)
        ->and(fn () => loyalty()->applyReferral($ben, $ben->referral_code))->toThrow(ValidationException::class);
    loyalty()->applyReferral($ben, strtolower($maria->referral_code));
    expect(fn () => loyalty()->applyReferral($ben->refresh(), $maria->referral_code))->toThrow(ValidationException::class);

    completedOrder($friend); // 5 pts + 200 referral
    completedOrder($friend); // no second referral bonus

    expect(memberOf($friend)->points_balance)->toBe(210)
        ->and($maria->refresh()->points_balance)->toBe(205)
        ->and(fn () => loyalty()->applyReferral(memberOf($this->guest), memberOf($friend)->referral_code))->toThrow(ValidationException::class); // already earning
});

it('redeems rewards into store credit or a personal coupon', function () {
    completedOrder($this->guest, 40); // 200 pts
    $member = memberOf($this->guest);
    $credit = Reward::create(['name' => '₱500 credit', 'points_cost' => 150, 'kind' => 'credit', 'credit_amount' => 500]);
    $promo = Promotion::create(['code' => 'LOYAL', 'name' => 'Loyal', 'type' => 'fixed', 'value' => 100, 'applies_to' => Promotion::FOR_ORDERS]);
    $coupon = Reward::create(['name' => '₱100 off', 'points_cost' => 50, 'kind' => 'coupon', 'promotion_id' => $promo->id]);

    $result = loyalty()->redeem($member, $credit, $this->owner);
    $card = GiftCard::query()->where('code', $result['code'])->firstOrFail();
    expect($card->kind)->toBe('credit')->and((float) $card->balance)->toBe(500.0)->and($member->refresh()->points_balance)->toBe(50);

    $second = loyalty()->redeem($member, $coupon, $this->owner);
    expect(Coupon::query()->where('code', $second['code'])->exists())->toBeTrue()
        ->and(fn () => loyalty()->redeem($member->refresh(), $coupon, $this->owner))->toThrow(ValidationException::class); // 0 points left
});

it('spends gift cards and credit at the POS and on folios, with the books following', function () {
    $cards = app(GiftCardService::class);
    $card = $cards->sell(1000, 'cash', $this->owner);

    $pos = app(PosService::class);
    $pos->openSession($this->restaurant, 0, $this->owner);
    $ticket = app(OrderService::class)->placeAtRegister($this->restaurant->refresh(), [['item_id' => $this->item->id, 'quantity' => 1]], null, $this->owner);
    $pos->pay($ticket, 'gift_card', 300, $this->owner, reference: strtolower($card->code));
    expect((float) $card->refresh()->balance)->toBe(700.0)
        ->and(fn () => $pos->pay($ticket->refresh(), 'gift_card', 200, $this->owner, reference: 'GC-FAKE-CODE'))->toThrow(ValidationException::class);
    $pos->pay($ticket->refresh(), 'cash', 200, $this->owner, tendered: 200);
    $pos->close($ticket->refresh());

    $stay = BookingFixtures::reserve($this->property, $this->type);
    app(BookingService::class)->transition($stay, Booking::CHECKED_IN);
    app(FolioService::class)->recordPayment($stay, 'gift_card', 700, $this->owner, $card->code);
    expect((float) $card->refresh()->balance)->toBe(0.0)
        ->and(fn () => app(FolioService::class)->recordPayment($stay, 'gift_card', 1, $this->owner, $card->code))->toThrow(ValidationException::class);

    $leftover = $cards->sell(250, 'bank', $this->owner);
    $cards->void($leftover);

    app(PostingService::class)->sync();
    $ledger = app(LedgerService::class);
    expect($ledger->balance('gift_card_liability'))->toBe(0.0) // 1,250 sold − 300 POS − 700 folio − 250 voided
        ->and($ledger->balance('cash'))->toBe(1200.0)       // 1,000 card sale + 200 cash at the till
        ->and(collect($ledger->profitAndLoss('2030-01-01', '2030-12-31')['revenue'])->firstWhere('name', 'Other revenue')['amount'])->toBe(250.0);
});

it('shows members their rewards and runs the host screens with permissions', function () {
    checkedOutStay($this->guest);
    $member = memberOf($this->guest);

    MarketplaceFixtures::asTenant(null);
    $this->actingAs($this->guest)->get(route('account.loyalty'))->assertOk()->assertSee('Hotel A')->assertSee('125 points')->assertSee($member->referral_code);

    PropertyManagementFixtures::login($this->owner, $this->tenant);
    $this->get(route('loyalty.index'))->assertOk()->assertSee('Maria Santos')->assertSee('Bronze');
    $this->post(route('loyalty.rewards.store'), ['name' => 'Free dessert', 'points_cost' => 30, 'kind' => 'credit', 'credit_amount' => 150])->assertSessionHasNoErrors();
    $this->post(route('loyalty.members.redeem', $member->id), ['reward_id' => Reward::query()->value('id')])->assertSessionHasNoErrors();
    $this->post(route('loyalty.gift-cards.store'), ['amount' => 2000, 'paid_via' => 'cash'])->assertSessionHasNoErrors();
    $this->get(route('loyalty.members.show', $member->id))->assertOk()->assertSee('Redeemed: Free dessert');

    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'front_desk'), $this->tenant);
    $this->get(route('loyalty.index'))->assertOk();
    $this->post(route('loyalty.gift-cards.store'), ['amount' => 100, 'paid_via' => 'cash'])->assertForbidden();

    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'staff'), $this->tenant);
    $this->get(route('loyalty.index'))->assertForbidden();
});
