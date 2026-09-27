<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulePlan extends Model
{
    protected $fillable = [
        'module_id', 'name', 'price_cents', 'currency', 'billing_interval',
        'limits', 'features', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function formattedPrice(): string
    {
        if ($this->price_cents === 0) {
            return 'Free';
        }
        return \App\Support\Currency::symbol().number_format($this->price_cents / 100, 2);
    }
}