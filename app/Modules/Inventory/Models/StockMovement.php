<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Append-only ledger line: a signed change to one level, with the balance after it. */
#[Fillable([
    'tenant_id', 'inventory_item_id', 'stock_location_id', 'type', 'quantity', 'balance_after',
    'unit_cost', 'reference', 'source_key', 'notes', 'user_id',
])]
class StockMovement extends Model
{
    use BelongsToTenant;

    public const TYPES = ['receipt', 'issue', 'adjustment', 'transfer_in', 'transfer_out', 'waste', 'sale', 'sale_return'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'balance_after' => 'decimal:3', 'unit_cost' => 'decimal:4'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return Str::headline($this->type);
    }
}
