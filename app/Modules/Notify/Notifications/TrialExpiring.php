<?php

namespace App\Modules\Notify\Notifications;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\Notify\ChannelNotification;

/** A module trial (the business's subscription until billing, Phase 27) ends soon. */
class TrialExpiring extends ChannelNotification
{
    public function __construct(private readonly TenantModule $subscription, private readonly Tenant $tenant) {}

    public function event(): string
    {
        return 'trial_expiring';
    }

    public function message(): string
    {
        return 'The '.$this->subscription->module?->name.' trial for '.$this->tenant->name.' ends '.$this->subscription->trial_ends_at->diffForHumans().' ('.$this->subscription->trial_ends_at->format('M j').').';
    }

    public function link(): ?string
    {
        return route('dashboard');
    }

    public function data(): array
    {
        return ['tenant_module_id' => $this->subscription->id, 'ends' => $this->subscription->trial_ends_at->toDateString()];
    }
}
