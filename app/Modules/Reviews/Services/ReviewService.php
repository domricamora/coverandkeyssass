<?php

namespace App\Modules\Reviews\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Marketplace\Models\Review;
use App\Modules\Ordering\Models\Order;
use App\Modules\Reviews\Notifications\ReviewReplied;
use App\Modules\RestaurantManagement\Models\TableReservation;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reviews (Phase 24). Guests review what they actually did — a checked-out
 * stay (property + room type), a completed food order (restaurant + each
 * dish) or a table visit — once each; verified reviews go live at once.
 * Hosts reply and can flag abuse; platform admins moderate.
 *
 * Aggregates (overall / category / room type / dish / host) are computed
 * from published reviews only; the listing's avg_rating & reviews_count
 * stay maintained by the Marketplace ReviewObserver.
 */
class ReviewService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data rating, title, comment, rating_{category} */
    public function reviewStay(Booking $booking, User $user, array $data): Review
    {
        if ((int) $booking->user_id !== (int) $user->id || ! $booking->reviewable()) {
            $this->fail('rating', 'You can review a stay once you have checked out.');
        }

        return $this->create($booking->property, $user, $data, ['booking_id' => $booking->id, 'room_type_id' => $booking->rooms()->value('room_type_id')], ['cleanliness', 'location', 'service', 'value', 'amenities']);
    }

    /** @param array<int, int> $itemRatings menu_item_id => stars */
    public function reviewOrder(Order $order, User $user, array $data, array $itemRatings = []): Review
    {
        if ((int) $order->user_id !== (int) $user->id || $order->status !== Order::COMPLETED) {
            $this->fail('rating', 'You can review an order once it is completed.');
        }

        return DB::transaction(function () use ($order, $user, $data, $itemRatings) {
            $review = $this->create($order->restaurant, $user, $data, ['order_id' => $order->id], ['food', 'service', 'value']);
            $ordered = $order->items()->whereNotNull('menu_item_id')->pluck('menu_item_id')->all();

            foreach ($itemRatings as $itemId => $stars) {
                if (in_array((int) $itemId, $ordered, true) && (int) $stars >= 1 && (int) $stars <= 5) {
                    $review->itemRatings()->create(['menu_item_id' => (int) $itemId, 'rating' => (int) $stars]);
                }
            }

            return $review;
        });
    }

    public function reviewVisit(TableReservation $visit, User $user, array $data): Review
    {
        if ((int) $visit->user_id !== (int) $user->id || ! in_array($visit->status, [TableReservation::SEATED, TableReservation::COMPLETED], true)) {
            $this->fail('rating', 'You can review a visit once you have been seated.');
        }

        return $this->create($visit->restaurant, $user, $data, ['table_reservation_id' => $visit->id], ['food', 'service', 'value', 'cleanliness']);
    }

    public function reply(Review $review, string $text, User $host): Review
    {
        $first = $review->host_response === null;
        $review->forceFill(['host_response' => $text, 'responded_at' => now()])->save();

        if ($first) {
            $review->user?->notify(new ReviewReplied($review));
        }

        $this->audit->log('review.replied', $review, null, ['by' => $host->id]);

        return $review;
    }

    /** Host reports a review for the platform to look at; it stays visible until moderated. */
    public function flag(Review $review, string $reason): Review
    {
        $review->forceFill(['flagged_at' => now(), 'flag_reason' => $reason])->save();
        $this->audit->log('review.flagged', $review, null, ['reason' => $reason]);

        return $review;
    }

    /** Platform moderation: publish (clears the flag) or reject (hides it and drops it from ratings). */
    public function moderate(Review $review, bool $publish, User $admin, ?string $note = null): Review
    {
        $review->forceFill([
            'status' => $publish ? Review::STATUS_PUBLISHED : Review::STATUS_REJECTED,
            'published_at' => $publish ? ($review->published_at ?? now()) : $review->published_at,
            'flagged_at' => null,
            'moderated_by' => $admin->id,
            'moderation_note' => $note,
        ])->save();

        $this->audit->log('review.'.($publish ? 'published' : 'rejected'), $review, null, ['note' => $note], $review->tenant_id);

        return $review;
    }

    /**
     * Published-review figures for a listing page.
     *
     * @return array{count: int, overall: float, categories: array<string, float>, rooms: array<string, array{0: float, 1: int}>, dishes: array<string, array{0: float, 1: int}>, host: ?float}
     */
    public function summary(Model $listing): array
    {
        $reviews = Review::query()->published()->where('reviewable_type', $listing->getMorphClass())->where('reviewable_id', $listing->getKey());

        $categories = collect(Review::CATEGORIES)
            ->mapWithKeys(fn ($c) => [$c => (clone $reviews)->whereNotNull('rating_'.$c)->avg('rating_'.$c)])
            ->filter()->map(fn ($v) => round((float) $v, 1))->all();

        $rooms = (clone $reviews)->whereNotNull('room_type_id')->with('roomType')->get()->groupBy(fn ($r) => $r->roomType?->name ?? 'Room')
            ->map(fn ($rows) => [round((float) $rows->avg('rating'), 1), $rows->count()])->all();

        $dishes = \App\Modules\Reviews\Models\ReviewItemRating::query()->whereIn('review_id', (clone $reviews)->pluck('id'))->with('menuItem')->get()
            ->groupBy(fn ($r) => $r->menuItem?->name ?? 'Dish')->map(fn ($rows) => [round((float) $rows->avg('rating'), 1), $rows->count()])
            ->sortByDesc(fn ($d) => $d[0])->all();

        $host = $listing->tenant_id ? Review::query()->published()->where('tenant_id', $listing->tenant_id)->avg('rating') : null;

        return [
            'count' => (clone $reviews)->count(),
            'overall' => round((float) (clone $reviews)->avg('rating'), 1),
            'categories' => $categories,
            'rooms' => $rooms,
            'dishes' => $dishes,
            'host' => $host !== null ? round((float) $host, 1) : null,
        ];
    }

    // ------------------------------------------------------------------

    /** @param list<string> $categories the category ratings this kind of review asks for */
    private function create(Model $listing, User $user, array $data, array $link, array $categories): Review
    {
        [$column, $id] = [array_key_first($link), reset($link)];

        if (Review::query()->withTrashed()->where($column, $id)->exists()) {
            $this->fail('rating', 'You have already reviewed this.');
        }

        $ratings = collect($categories)->mapWithKeys(fn ($c) => ['rating_'.$c => isset($data['rating_'.$c]) ? max(1, min(5, (int) $data['rating_'.$c])) : null])->all();

        return Review::create($link + $ratings + [
            'tenant_id' => $listing->tenant_id,
            'user_id' => $user->id,
            'reviewable_type' => $listing->getMorphClass(),
            'reviewable_id' => $listing->getKey(),
            'rating' => max(1, min(5, (int) $data['rating'])),
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'status' => Review::STATUS_PUBLISHED, // verified purchase — live at once, moderated after the fact
            'published_at' => now(),
        ]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
