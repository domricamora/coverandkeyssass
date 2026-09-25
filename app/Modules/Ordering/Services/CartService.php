<?php

namespace App\Modules\Ordering\Services;

use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Contracts\Session\Session;

/**
 * Session cart (Phase 11): one restaurant at a time. It stores only ids and
 * quantities; prices are recomputed by OrderService::quote() on every
 * render and again at checkout.
 */
class CartService
{
    private const KEY = 'ordering.cart';

    public function __construct(private readonly Session $session) {}

    /** @return array{restaurant_id: ?int, lines: array<string, array{item_id: int, option_ids: list<int>, quantity: int, notes: ?string}>} */
    public function get(): array
    {
        return $this->session->get(self::KEY, ['restaurant_id' => null, 'lines' => []]);
    }

    /** Add a line; returns true when a cart from another restaurant was replaced. */
    public function add(Restaurant $restaurant, int $itemId, array $optionIds, int $quantity, ?string $notes): bool
    {
        $cart = $this->get();
        $replaced = $cart['restaurant_id'] !== null && $cart['restaurant_id'] !== $restaurant->id && $cart['lines'] !== [];

        if ($cart['restaurant_id'] !== $restaurant->id) {
            $cart = ['restaurant_id' => $restaurant->id, 'lines' => []];
        }

        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
        sort($optionIds);
        $key = md5($itemId.'|'.implode(',', $optionIds).'|'.trim((string) $notes));

        $cart['lines'][$key] = [
            'item_id' => $itemId,
            'option_ids' => $optionIds,
            'quantity' => min(OrderService::MAX_QUANTITY, ($cart['lines'][$key]['quantity'] ?? 0) + $quantity),
            'notes' => filled($notes) ? trim($notes) : null,
        ];

        $this->session->put(self::KEY, $cart);

        return $replaced;
    }

    public function update(string $key, int $quantity): void
    {
        $cart = $this->get();

        if ($quantity <= 0) {
            unset($cart['lines'][$key]);
        } elseif (isset($cart['lines'][$key])) {
            $cart['lines'][$key]['quantity'] = min(OrderService::MAX_QUANTITY, $quantity);
        }

        $this->session->put(self::KEY, $cart);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }
}
