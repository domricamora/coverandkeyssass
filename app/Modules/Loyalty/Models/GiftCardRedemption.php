<?php

namespace App\Modules\Loyalty\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'gift_card_id', 'amount', 'reference'])]
class GiftCardRedemption extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
