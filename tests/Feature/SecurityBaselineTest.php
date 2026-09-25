<?php

use App\Models\User;

/*
| Phase 32 (Security audit) — the baseline that must not regress: security
| headers + CSP on web and API responses, password policy, the secure session
| cookie in production, and suspended accounts locked out of the web login.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends security headers and a restrictive CSP on web and API responses', function () {
    foreach (['/', '/api/v1/properties'] as $url) {
        $response = $this->get($url)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toContain("object-src 'none'")
            ->toContain("frame-ancestors 'self'")
            ->toContain("base-uri 'self'")
            ->toContain('form-action \'self\' https://*.paymongo.com');
    }
});

it('rejects weak passwords at registration', function () {
    $this->post('/register', [
        'name' => 'Weak Pass',
        'email' => 'weak@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'weak@example.test')->exists())->toBeFalse();
});

it('marks the session cookie secure in production by default', function () {
    // config/session.php reads the APP_ENV variable, as a real production host sets it.
    $previous = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null];
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'production';

    try {
        expect((require config_path('session.php'))['secure'])->toBeTrue();
    } finally {
        [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = $previous;
    }
});

it('keeps suspended accounts out of the web login', function () {
    $user = User::factory()->create(['status' => 'suspended']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});
