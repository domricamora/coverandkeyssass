<?php

namespace App\Modules\Marketplace\Requests;

use App\Modules\Marketplace\Services\MarketplaceSearchService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates marketplace search/filter input. Public (unauthenticated) route,
 * so it is read-only by construction and every value is whitelisted here
 * before it reaches the query builder.
 */
class PropertySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:80'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'amenities' => ['nullable', 'array', 'max:20'],
            'amenities.*' => ['string', 'max:60'],
            'cuisines' => ['nullable', 'array', 'max:20'],
            'cuisines.*' => ['string', 'max:60'],
            'price_level' => ['nullable', 'integer', 'min:1', 'max:4'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', MarketplaceSearchService::SORTS)],
            // Date search (total price for the stay, only stays with a room free every night).
            'check_in' => ['nullable', 'date', 'after_or_equal:today', 'required_with:check_out'],
            'check_out' => ['nullable', 'date', 'after:check_in', 'required_with:check_in', 'before:'.now()->addYear()->toDateString()],
            'free_cancellation' => ['nullable', 'boolean'],
            'min_rating' => ['nullable', 'numeric', 'in:3,3.5,4,4.5'],
        ];
    }
}