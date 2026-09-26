<?php

use App\Models\User;

/*
| Guest booking flows (React widgets + checkout pages): sign-in return,
| stay quotes, the review step, cart JSON and the table booking widget.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns a guest to the step they left after signing in', function () {
    $this->get('/continue?to=/cart')->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/continue?to=%2Fcart');

    $this->get('/continue?to=/cart')->assertRedirect('/cart');
});

it('only follows same-site paths', function () {
    $this->actingAs(User::factory()->create());

    foreach (['https://evil.test/x', '//evil.test', '/\\evil.test', 'cart'] as $to) {
        $this->get('/continue?to='.urlencode($to))->assertRedirect('/');
    }
});
