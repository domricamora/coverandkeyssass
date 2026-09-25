<?php

namespace App\Modules\Reviews\Notifications;

use App\Modules\Marketplace\Models\Review;
use Illuminate\Notifications\Notification;

/** Tells a guest the business answered their review. */
class ReviewReplied extends Notification
{
    public function __construct(private readonly Review $review) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'review_id' => $this->review->id,
            'message' => ($this->review->reviewable?->name ?? 'The business').' replied to your review.',
        ];
    }
}
