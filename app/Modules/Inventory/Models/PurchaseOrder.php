<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** draft → ordered → (partially_received →) received; draft / ordered → cancelled. */
#[Fillable(['tenant_id', 'supplier_id', 'stock_location_id', 'reference', 'status', 'expected_on', 'notes', 'total', 'created_by'])]
class PurchaseOrder extends Model
{
    use BelongsToTenant;

    public const DRAFT = 'draft';

    public const ORDERED = 'ordered';

    public const PARTIAL = 'partially_received';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    protected $attributes = ['status' => self::DRAFT];

    protected function casts(): array
    {
        return ['expected_on' => 'date', 'total' => 'decimal:2', 'ordered_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public static function newReference(): string
    {
        do {
            $reference = 'PO'.Str::upper(Str::random(8));
        } while (static::query()->withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function statusLabel(): string
    {
        return Str::headline($this->status);
    }
}
