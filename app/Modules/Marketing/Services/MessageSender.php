<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Booking\Models\Promotion;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Services\CrmService;
use App\Modules\Marketing\Mail\MarketingMessage;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Marketing\Support\SmsSender;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Delivers one personalised message and records it in the CRM
 * communication history under a source key — the log doubles as the
 * "already sent" check, so every sender is safe to re-run.
 *
 * Placeholders: {name} {business} {link} {coupon}.
 */
class MessageSender
{
    public function __construct(
        private readonly CrmService $crm,
        private readonly SmsSender $sms,
    ) {}

    public function alreadySent(string $sourceKey): bool
    {
        return \App\Modules\Crm\Models\Interaction::query()->where('source_key', $sourceKey)->exists();
    }

    /**
     * @param  array{link?: string}  $vars
     * @return string|null the coupon code issued, if any
     */
    public function send(Contact $contact, string $channel, ?string $subject, string $body, string $sourceKey, ?Promotion $promotion = null, array $vars = [], bool $marketing = true): ?string
    {
        $address = $channel === 'sms' ? $contact->phone : $contact->email;

        if (! $address || $this->alreadySent($sourceKey)) {
            return null;
        }

        $coupon = $promotion ? Coupon::create(['promotion_id' => $promotion->id, 'crm_contact_id' => $contact->id, 'code' => Coupon::newCode($promotion)]) : null;
        $business = app(TenantContext::class)->tenant()?->name ?? config('app.name');

        $text = strtr($body, [
            '{name}' => strtok($contact->name, ' ') ?: $contact->name,
            '{business}' => $business,
            '{link}' => $vars['link'] ?? '',
            '{coupon}' => $coupon ? 'Use code '.$coupon->code.' for '.$promotion->label().'.' : '',
        ]);

        if ($channel === 'sms') {
            $this->sms->send($address, $text.($marketing ? ' Reply STOP to opt out.' : ''));
        } else {
            $unsubscribe = $marketing ? URL::signedRoute('marketing.unsubscribe', ['tenant' => $contact->tenant_id, 'contact' => $contact->id]) : null;
            Mail::to($address)->send(new MarketingMessage($subject ?? $business, $text, $business, $unsubscribe));
        }

        $this->crm->log($contact, $channel, 'outbound', $subject, $text, null, $sourceKey);

        return $coupon?->code;
    }
}
