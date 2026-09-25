<?php

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;

/*
| Phase 31 (API v1) — Sanctum tokens (issue / abilities / revoke / throttle),
| the public catalogue, the customer surface and the business surface with
| X-Tenant isolation and dashboard permissions.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel(rooms: 2);
    MarketplaceFixtures::publish($this->property);
    MarketplaceFixtures::asTenant(null);
    Auth::logout(); // fixtures sign in on the web guard; the API must rely on tokens only
});

/**
 * Issue a token through the real endpoint. In-process tests share one app, so
 * the previous Bearer header and the guard's cached user must be dropped —
 * each production request gets a fresh app and never needs this.
 */
function apiToken(User $user, bool $readOnly = false): string
{
    test()->flushHeaders();
    Auth::forgetGuards();

    $token = test()->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'test', 'read_only' => $readOnly])
        ->assertCreated()->json('token');

    Auth::forgetGuards();

    return $token;
}

it('issues, scopes, revokes and throttles tokens without revealing which emails exist', function () {
    $unknown = $this->postJson('/api/v1/auth/token', ['email' => 'nobody@example.test', 'password' => 'x', 'device_name' => 't'])->assertUnprocessable()->json('errors.email.0');
    $wrong = $this->postJson('/api/v1/auth/token', ['email' => $this->owner->email, 'password' => 'nope', 'device_name' => 't'])->assertUnprocessable()->json('errors.email.0');
    expect($unknown)->toBe($wrong);

    $suspended = User::factory()->create(['status' => 'suspended']);
    $this->postJson('/api/v1/auth/token', ['email' => $suspended->email, 'password' => 'password', 'device_name' => 't'])->assertUnprocessable();

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();

    $token = apiToken($this->owner);
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()
        ->assertJsonPath('businesses.0.slug', $this->tenant->slug)
        ->assertJsonPath('token_abilities', ['read', 'write']);

    $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertNoContent();
    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

    // Six attempts a minute, then 429.
    foreach (range(1, 6) as $i) {
        $this->postJson('/api/v1/auth/token', ['email' => $this->owner->email, 'password' => 'bad', 'device_name' => 't']);
    }
    $this->postJson('/api/v1/auth/token', ['email' => $this->owner->email, 'password' => 'password', 'device_name' => 't'])->assertTooManyRequests();
});

it('serves the public catalogue from published listings only', function () {
    [$draft] = MarketplaceFixtures::stay(['name' => 'Secret Draft', 'status' => 'draft'], 'Other Co');
    MarketplaceFixtures::asTenant(null);

    $this->getJson('/api/v1/properties')->assertOk()
        ->assertJsonFragment(['slug' => $this->property->slug])
        ->assertJsonMissing(['slug' => $draft->slug]);
    $this->getJson('/api/v1/properties/'.$draft->slug)->assertNotFound();
    $this->getJson('/api/v1/properties/'.$this->property->slug.'/rooms')->assertOk()
        ->assertJsonPath('data.0.id', $this->type->id)
        ->assertJsonMissingPath('data.0.tenant_id');
});

it('lets a customer book through the API and see only their own stays; read-only tokens cannot write', function () {
    $guest = User::factory()->create();
    $payload = ['check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(12)->toDateString(), 'room_type_id' => $this->type->id, 'quantity' => 1, 'adults' => 2];

    $this->withToken(apiToken($guest, readOnly: true))->postJson('/api/v1/properties/'.$this->property->slug.'/bookings', $payload)->assertForbidden();
    Auth::forgetGuards();

    $reference = $this->withToken(apiToken($guest))->postJson('/api/v1/properties/'.$this->property->slug.'/bookings', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', Booking::PENDING)
        ->assertJsonPath('data.source', Booking::SOURCE_MARKETPLACE)
        ->json('data.reference');

    $this->getJson('/api/v1/me/bookings')->assertOk()->assertJsonPath('data.0.reference', $reference);

    Auth::forgetGuards();
    $stranger = User::factory()->create();
    $this->withToken(apiToken($stranger))->getJson('/api/v1/me/bookings/'.$reference)->assertNotFound();
});

it('scopes the business API to X-Tenant membership and dashboard permissions', function () {
    $booking = BookingFixtures::reserve($this->property, $this->type, ['check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(5)->toDateString(), 'hold_hours' => 2]);
    MarketplaceFixtures::asTenant(null);
    $token = apiToken($this->owner);

    $this->withToken($token)->getJson('/api/v1/business/bookings')->assertNotFound();                       // no header
    $this->withToken($token)->withHeader('X-Tenant', 'someone-else')->getJson('/api/v1/business/bookings')->assertNotFound();

    [, $otherTenant] = MarketplaceFixtures::business('Rival Inn');
    MarketplaceFixtures::asTenant(null);
    $this->withToken($token)->withHeader('X-Tenant', $otherTenant->slug)->getJson('/api/v1/business/bookings')->assertNotFound();

    $this->withToken($token)->withHeader('X-Tenant', $this->tenant->slug)->getJson('/api/v1/business/bookings')->assertOk()
        ->assertJsonPath('data.0.reference', $booking->reference);

    $this->withToken($token)->withHeader('X-Tenant', $this->tenant->slug)
        ->postJson('/api/v1/business/bookings/'.$booking->reference.'/status', ['status' => Booking::CONFIRMED])
        ->assertOk()->assertJsonPath('data.status', Booking::CONFIRMED);

    Auth::forgetGuards();
    $housekeeper = MarketplaceFixtures::member($this->tenant, 'staff');
    $this->withToken(apiToken($housekeeper))->withHeader('X-Tenant', $this->tenant->slug)
        ->getJson('/api/v1/business/bookings')->assertForbidden();
});
