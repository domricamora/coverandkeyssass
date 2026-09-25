<?php

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use Illuminate\Support\Facades\Log;
use Tests\Support\BookingFixtures;

/*
| Phase 37 (Monitoring) — the logs an operator reads: login attempts and
| forged webhooks in `security`, business events mirrored from the audit trail
| in `ops`, with secrets and personal data kept out.
*/

beforeEach(function () {
    foreach (['ops', 'security'] as $channel) {
        $this->{$channel} = sys_get_temp_dir().DIRECTORY_SEPARATOR."ck-{$channel}-".uniqid().'.log';
        config(["logging.channels.{$channel}" => ['driver' => 'single', 'path' => $this->{$channel}, 'level' => 'debug']]);
        Log::forgetChannel($channel);
    }
});

afterEach(fn () => array_map(fn ($f) => @unlink($f), [$this->ops, $this->security]));

function logText(string $path): string
{
    return is_file($path) ? file_get_contents($path) : '';
}

it('logs failed logins without the password', function () {
    User::factory()->create(['email' => 'Maria@Example.test']);

    $this->post('/login', ['email' => 'Maria@Example.test', 'password' => 'Wrong-Horse-99'])->assertSessionHasErrors('email');

    expect(logText($this->security))->toContain('auth.login_failed')
        ->toContain('maria@example.test')
        ->toContain('"known_user":true')
        ->not->toContain('Wrong-Horse-99');
});

it('logs forged webhooks as a security signal', function () {
    config(['services.paymongo.webhook_secret' => 'whsk_test']);

    $this->call('POST', '/webhooks/paymongo', [], [], [], ['HTTP_PAYMONGO_SIGNATURE' => 't=1,te=bad'], '{"data":{}}')->assertStatus(400);

    expect(logText($this->security))->toContain('webhook.paymongo.invalid_signature');
});

it('mirrors audited business events to the ops log without personal data', function () {
    BookingFixtures::bootstrap();
    [, , $property, $type] = BookingFixtures::hotel();

    $booking = BookingFixtures::reserve($property, $type, ['hold_hours' => 2, 'guest_email' => 'private.guest@example.test']);
    app(BookingService::class)->asTenantOf($property, fn () => app(BookingService::class)->transition($booking, Booking::CANCELLED, 'Guest changed plans'));

    $ops = logText($this->ops);

    expect($ops)->toContain('booking.created')
        ->toContain('NOTICE: booking.cancelled')                            // cancellations stand out
        ->toContain('"tenant_id":'.$property->tenant_id)
        ->not->toContain('private.guest@example.test')
        ->not->toContain('Guest changed plans');
});
