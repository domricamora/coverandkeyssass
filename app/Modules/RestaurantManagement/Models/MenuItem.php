<?php

namespace App\Modules\RestaurantManagement\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Marketplace\Models\Restaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * A dish or drink on the menu ("Burger ₱250") with optional modifier
 * groups ("Add-ons": Cheese +₱30, Bacon +₱50, Egg +₱25).
 */
#[Fillable([
    'tenant_id', 'restaurant_id', 'menu_category_id', 'name', 'description',
    'price', 'currency', 'photo_url', 'is_available', 'sort_order',
])]
class MenuItem extends Model
{
    use BelongsToTenant;
    use \App\Modules\Marketplace\Models\Concerns\HasMedia; // dish photos

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_available' => 'boolean', 'sort_order' => 'integer'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function priceLabel(): string
    {
        return self::money((float) $this->price, $this->currency);
    }

    public static function money(float $amount, string $currency = 'PHP'): string
    {
        return \App\Support\Currency::format($amount);
    }

    /**
     * Unit price with the chosen modifier options, enforcing each group's
     * min/max and option availability. Ordering (Phase 11) prices lines
     * through this so the server never trusts a client total.
     *
     * @param  list<int>  $optionIds
     *
     * @throws ValidationException
     */
    public function priceWith(array $optionIds): string
    {
        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
        $groups = $this->modifierGroups()->with('options')->get();
        $total = (float) $this->price;
        $matched = 0;

        foreach ($groups as $group) {
            $chosen = $group->options->whereIn('id', $optionIds);

            if ($chosen->contains(fn (ModifierOption $o) => ! $o->is_available)) {
                throw ValidationException::withMessages(['modifiers' => "An option in \"{$group->name}\" is unavailable."]);
            }
            if ($chosen->count() < $group->min_select) {
                throw ValidationException::withMessages(['modifiers' => "Choose at least {$group->min_select} from \"{$group->name}\"."]);
            }
            if ($group->max_select !== null && $chosen->count() > $group->max_select) {
                throw ValidationException::withMessages(['modifiers' => "Choose at most {$group->max_select} from \"{$group->name}\"."]);
            }

            $matched += $chosen->count();
            $total += (float) $chosen->sum('price');
        }

        if ($matched !== count($optionIds)) {
            throw ValidationException::withMessages(['modifiers' => 'An option does not belong to this item.']);
        }

        return number_format($total, 2, '.', '');
    }
}
