<?php

use App\Modules\Marketing\Support\SmsSender;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
| SMS carriers (closing the Phase 38 INCOMPLETE item): Semaphore and Twilio
| drivers send the right request, and a carrier error throws so the queued
| notification is retried instead of silently lost.
*/

it('sends through Semaphore', function () {
    config(['services.sms.driver' => 'semaphore', 'services.sms.semaphore.key' => 'sem_key', 'services.sms.semaphore.sender' => 'COVERKEYS']);
    Http::fake(['api.semaphore.co/*' => Http::response([['message_id' => 1]])]);

    app(SmsSender::class)->send('09171234567', 'Your booking is confirmed.');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.semaphore.co/api/v4/messages'
        && $r['apikey'] === 'sem_key' && $r['number'] === '09171234567'
        && $r['message'] === 'Your booking is confirmed.' && $r['sendername'] === 'COVERKEYS');
});

it('sends through Twilio with basic auth', function () {
    config(['services.sms.driver' => 'twilio', 'services.sms.twilio.sid' => 'AC123', 'services.sms.twilio.token' => 'tok', 'services.sms.twilio.from' => '+15550001111']);
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

    app(SmsSender::class)->send('+639171234567', 'Table for 2 at 19:00.');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('AC123:tok'))
        && $r['To'] === '+639171234567' && $r['From'] === '+15550001111' && $r['Body'] === 'Table for 2 at 19:00.');
});

it('throws on carrier errors and on missing credentials so the worker retries', function () {
    config(['services.sms.driver' => 'semaphore', 'services.sms.semaphore.key' => 'sem_key']);
    Http::fake(['api.semaphore.co/*' => Http::response(['error' => 'insufficient credits'], 402)]);

    expect(fn () => app(SmsSender::class)->send('0917', 'x'))->toThrow(RequestException::class);

    config(['services.sms.driver' => 'twilio', 'services.sms.twilio.sid' => null]);
    expect(fn () => app(SmsSender::class)->send('0917', 'x'))->toThrow(RuntimeException::class, 'TWILIO_SID');
});
