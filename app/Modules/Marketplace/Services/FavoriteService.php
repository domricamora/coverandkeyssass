<?php

namespace App\Modules\Marketplace\Services;

use App\Models\User;
use App\Modules\Marketplace\Models\Favorite;
use Illuminate\Database\Eloquent\Model;

/**
 * Wish-list operations. Always scoped to a single user: a favorite is
 * personal data and is never read or written across users.
 */
class FavoriteService
{
    /**
     * Ids from $candidateIds that this user has favorited (for card state).
     *
     * @param  array<int, int|string>  $candidateIds
     * @return array<int, int>
     */
    public function favoritedIds(?User $user, string $morphType, array $candidateIds): array
    {
        if (! $user || $candidateIds === []) {
            return [];
        }

        return Favorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', $morphType)
            ->whereIn('favoritable_id', $candidateIds)
            ->pluck('favoritable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Idempotent: favoriting twice never creates a duplicate row. */
    public function add(User $user, Model $listing): Favorite
    {
        return Favorite::firstOrCreate([
            'user_id' => $user->id,
            'favoritable_type' => $listing->getMorphClass(),
            'favoritable_id' => $listing->getKey(),
        ]);
    }

    public function remove(User $user, Model $listing): bool
    {
        return (bool) Favorite::query()
            ->where('user_id', $user->id)
            ->where('favoritable_type', $listing->getMorphClass())
            ->where('favoritable_id', $listing->getKey())
            ->delete();
    }
}