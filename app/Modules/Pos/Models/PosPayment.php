<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Ordering\Models\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money taken (or refunded, negative) at the register for an order. */
#[Fillable(['tenant_id', 'order_id', 'pos_session_id', 'method', 'amount', 'tendered', 'change_given', 'reference', 'user_id'])]
class PosPayment extends Model
{
    use BelongsToTenant;

    public const METHODS = ['cash', 'card', 'ewallet'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tendered' => 'decimal:2', 'change_given' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
