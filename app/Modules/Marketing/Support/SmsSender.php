<?php

namespace App\Modules\Marketing\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * SMS out. Drivers (config services.sms.driver / SMS_DRIVER):
 *
 *   log        writes to the log (default: local and staging)
 *   array      kept in memory (tests)
 *   semaphore  Semaphore (PH gateway): SEMAPHORE_API_KEY, SEMAPHORE_SENDER
 *   twilio     Twilio: TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM
 *
 * Carrier errors throw, so a queued notification is retried by the worker
 * instead of being dropped silently.
 */
class SmsSender
{
    /** @var list<array{to: string, text: string}> */
    public static array $sent = [];

    public function send(string $to, string $text): void
    {
        match (config('services.sms.driver', 'log')) {
            'array' => self::$sent[] = ['to' => $to, 'text' => $text],
            'semaphore' => $this->semaphore($to, $text),
            'twilio' => $this->twilio($to, $text),
            default => Log::info('SMS to '.$to.': '.$text),
        };
    }

    private function semaphore(string $to, string $text): void
    {
        $key = config('services.sms.semaphore.key') ?: throw new RuntimeException('SEMAPHORE_API_KEY is not set.');

        Http::asForm()->timeout(15)->post('https://api.semaphore.co/api/v4/messages', array_filter([
            'apikey' => $key,
            'number' => $to,
            'message' => $text,
            'sendername' => config('services.sms.semaphore.sender'),
        ]))->throw();
    }

    private function twilio(string $to, string $text): void
    {
        $sid = config('services.sms.twilio.sid') ?: throw new RuntimeException('TWILIO_SID is not set.');

        Http::asForm()->timeout(15)
            ->withBasicAuth($sid, (string) config('services.sms.twilio.token'))
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => config('services.sms.twilio.from'),
                'Body' => $text,
            ])->throw();
    }
}
