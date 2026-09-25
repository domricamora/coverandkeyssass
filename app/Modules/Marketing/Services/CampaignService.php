<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Support\Segments;
use App\Modules\Marketing\Models\Campaign;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Campaigns (Phase 22): only contacts who agreed to marketing and have an
 * address for the channel. Sending is resumable — each contact is
 * recorded once per campaign, so a crash mid-send or a second click
 * never double-sends.
 */
class CampaignService
{
    public function __construct(
        private readonly MessageSender $sender,
        private readonly AuditLogger $audit,
    ) {}

    public function audience(Campaign $campaign): Builder
    {
        $query = Contact::query()->where('marketing_consent', true)->whereNotNull($campaign->channel === 'sms' ? 'phone' : 'email');

        [$kind, $value] = array_pad(explode(':', $campaign->audience, 2), 2, null);

        return match ($kind) {
            'segment' => Segments::apply($query, (string) $value),
            'tag' => $query->whereHas('tags', fn ($t) => $t->whereKey((int) $value)),
            default => $query,
        };
    }

    public function schedule(Campaign $campaign, string $at): Campaign
    {
        if ($campaign->status !== Campaign::DRAFT) {
            $this->fail('Only drafts can be scheduled.');
        }

        $campaign->forceFill(['status' => Campaign::SCHEDULED, 'scheduled_at' => $at])->save();

        return $campaign;
    }

    public function send(Campaign $campaign): Campaign
    {
        if ($campaign->status === Campaign::SENT) {
            $this->fail('This campaign was already sent.');
        }

        $campaign->loadMissing('promotion');
        $done = $campaign->recipients()->pluck('crm_contact_id')->all();
        $sent = 0;

        $this->audience($campaign)->whereNotIn('id', $done)->orderBy('id')->each(function (Contact $contact) use ($campaign, &$sent): void {
            DB::transaction(function () use ($campaign, $contact, &$sent): void {
                $coupon = $this->sender->send($contact, $campaign->channel, $campaign->subject, $campaign->body, 'campaign:'.$campaign->id.':contact:'.$contact->id, $campaign->promotion);

                $campaign->recipients()->create([
                    'crm_contact_id' => $contact->id,
                    'address' => $campaign->channel === 'sms' ? $contact->phone : $contact->email,
                    'coupon_code' => $coupon,
                    'sent_at' => now(),
                ]);
                $sent++;
            });
        });

        $campaign->forceFill([
            'status' => Campaign::SENT,
            'sent_at' => now(),
            'sent_count' => $campaign->recipients()->count(),
            'skipped_count' => Contact::query()->count() - $campaign->recipients()->count(),
        ])->save();

        $this->audit->log('campaign.sent', $campaign, null, ['recipients' => $campaign->sent_count]);

        return $campaign;
    }

    /** Due scheduled campaigns (the marketing:run command). */
    public function runScheduled(): int
    {
        $due = Campaign::query()->where('status', Campaign::SCHEDULED)->where('scheduled_at', '<=', now())->get();
        $due->each(fn (Campaign $c) => $this->send($c));

        return $due->count();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['campaign' => $message]);
    }
}
