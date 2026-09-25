<?php

namespace App\Modules\Reviews\Notifications;

use App\Modules\Marketplace\Models\Review;
use App\Modules\Notify\ChannelNotification;

/** Tells a guest the business answered their review. */
class ReviewReplied extends ChannelNotification
{
    public function __construct(private readonly Review $review) {}

    public function event(): string
    {
        return 'review_replied';
    }

    public function message(): string
    {
        return ($this->review->reviewable()->withoutGlobalScope('tenant')->first()?->name ?? 'The business').' replied to your review.';
    }

    public function link(): ?string
    {
        return route('account.reviews');
    }

    public function data(): array
    {
        return ['review_id' => $this->review->id];
    }
}
