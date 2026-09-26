<?php

use App\Models\User;
use App\Notifications\LoginLink;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/*
| Checkout without registration: a new email gets an account and is signed
| in; an existing email must prove ownership (password or emailed link).
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates an account for a new email and signs the guest in', function () {
    $this->postJson(route('guest.identify'), ['name' => 'Ana Cruz', 'email' => 'Ana@Example.test'])
        ->assertOk()->assertJsonPath('status', 'signed_in')->assertJsonPath('name', 'Ana Cruz');

    $this->assertAuthenticated();
    expect(User::query()->where('email', 'ana@example.test')->exists())->toBeTrue();
});

it('never signs in to an existing account on the email alone', function () {
    $owner = User::factory()->create(['email' => 'ben@example.test']);

    $this->postJson(route('guest.identify'), ['name' => 'Someone', 'email' => 'BEN@example.test'])
        ->assertOk()->assertJsonPath('status', 'existing');
    $this->assertGuest();

    $this->postJson(route('guest.password'), ['email' => 'ben@example.test', 'password' => 'wrong'])->assertStatus(422);
    $this->assertGuest();

    $this->postJson(route('guest.password'), ['email' => 'ben@example.test', 'password' => 'password'])
        ->assertOk()->assertJsonPath('status', 'signed_in');
    $this->assertAuthenticatedAs($owner);
});

it('emails a signed sign-in link that returns to the same step', function () {
    Notification::fake();
    $owner = User::factory()->create(['email' => 'cy@example.test']);

    $this->postJson(route('login.link.send'), ['email' => 'cy@example.test', 'to' => '/cart'])->assertOk()->assertJsonPath('sent', true);
    $this->postJson(route('login.link.send'), ['email' => 'nobody@example.test'])->assertOk()->assertJsonPath('sent', true); // same answer

    $url = null;
    Notification::assertSentTo($owner, LoginLink::class, function (LoginLink $n) use ($owner, &$url) {
        $url = $n->toMail($owner)->actionUrl;

        return true;
    });
    Notification::assertCount(1);

    $this->get(route('login.link', ['user' => $owner->id, 'to' => '/cart']))->assertForbidden(); // unsigned
    $this->get($url)->assertRedirect('/cart');
    $this->assertAuthenticatedAs($owner);

    auth()->logout();
    $this->get(URL::temporarySignedRoute('login.link', now()->addMinutes(5), ['user' => $owner->id, 'to' => '//evil.test']))->assertRedirect('/');
});
