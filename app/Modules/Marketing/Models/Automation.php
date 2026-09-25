<?php

namespace App\Modules\Marketing\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Booking\Models\Promotion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A per-business follow-up (off until enabled): what to send, how long after the trigger. */
#[Fillable(['tenant_id', 'type', 'enabled', 'delay_hours', 'subject', 'body', 'promotion_id'])]
class Automation extends Model
{
    use BelongsToTenant;

    protected $table = 'marketing_automations';

    /**
     * type => [label, default delay (h), default subject, default body, marketing?]
     * Marketing follow-ups need the guest's consent; service follow-ups
     * (a booking left unpaid, a review request) do not.
     */
    public const DEFAULTS = [
        'abandoned_booking' => ['Abandoned booking', 2, 'Your stay is waiting', "Hi {name}, your booking at {business} is not confirmed yet. Complete it here: {link}", false],
        'abandoned_cart' => ['Abandoned cart', 2, 'Still hungry?', "Hi {name}, you left something in your cart at {business}. {link}", true],
        'review_request' => ['Review request', 24, 'How was your stay?', "Hi {name}, thank you for staying at {business}. We would love your review: {link}", false],
        'post_stay' => ['Post-stay offer', 168, 'Come back soon', "Hi {name}, we hope to see you again at {business}. {coupon}", true],
        'reactivation' => ['Customer reactivation', 0, 'We miss you', "Hi {name}, it has been a while! Here is something for your next visit at {business}. {coupon}", true],
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'delay_hours' => 'integer'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function label(): string
    {
        return self::DEFAULTS[$this->type][0] ?? $this->type;
    }

    public function needsConsent(): bool
    {
        return (bool) (self::DEFAULTS[$this->type][4] ?? true);
    }
}
