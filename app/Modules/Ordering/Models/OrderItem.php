<?php

namespace App\Modules\Ordering\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One order line: a snapshot of the item, its modifiers and prices at checkout. */
#[Fillable([
    'tenant_id', 'order_id', 'menu_item_id', 'name', 'modifiers', 'unit_price',
    'quantity', 'line_total', 'notes',
])]
class OrderItem extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'modifiers' => 'array',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function modifierLabel(): string
    {
        return collect($this->modifiers ?? [])->pluck('name')->implode(', ');
    }
}
