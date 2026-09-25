<?php

namespace App\Modules\Marketing\Support;

use Illuminate\Support\Facades\Log;

/**
 * SMS out. Drivers: `log` (default — writes to the log, for local and
 * staging) and `array` (kept in memory, for tests).
 *
 * ponytail: no real carrier yet; add a Semaphore / Twilio driver here
 * (config services.sms.driver) before sending SMS in production.
 */
class SmsSender
{
    /** @var list<array{to: string, text: string}> */
    public static array $sent = [];

    public function send(string $to, string $text): void
    {
        match (config('services.sms.driver', 'log')) {
            'array' => self::$sent[] = ['to' => $to, 'text' => $text],
            default => Log::info('SMS to '.$to.': '.$text),
        };
    }
}
