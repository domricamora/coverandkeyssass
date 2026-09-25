<?php

namespace App\Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One balanced posting (Σ debit = Σ credit). Never edited; corrections are reversing entries. */
#[Fillable(['tenant_id', 'entry_date', 'memo', 'reference', 'source_key', 'party_type', 'party_id', 'created_by'])]
class JournalEntry extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
