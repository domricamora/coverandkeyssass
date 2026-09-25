<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use App\Modules\Marketplace\Observers\ReviewObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Guest review of a listing. Only `published` reviews are ever shown
 * publicly, and the parent's avg_rating / reviews_count columns are
 * recalculated from published reviews only.
 */
#[Fillable([
    'tenant_id', 'user_id', 'booking_id', 'order_id', 'table_reservation_id', 'room_type_id',
    'reviewable_type', 'reviewable_id', 'rating', 'rating_cleanliness', 'rating_location', 'rating_service',
    'rating_value', 'rating_food', 'rating_amenities', 'title', 'comment',
    'status', 'host_response', 'responded_at', 'published_at',
])]
class Review extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'responded_at' => 'datetime',
            'published_at' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::observe(ReviewObserver::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Category ratings (Phase 24); overall stays in `rating`. */
    public const CATEGORIES = ['cleanliness', 'location', 'service', 'value', 'food', 'amenities'];

    public function itemRatings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Reviews\Models\ReviewItemRating::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\PropertyManagement\Models\RoomType::class)->withoutGlobalScope('tenant');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function publish(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => $this->published_at ?? now(),
        ])->save();
    }
}