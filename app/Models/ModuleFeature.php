<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleFeature extends Model
{
    protected $fillable = [
        'module_id', 'name', 'description', 'is_highlighted', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_highlighted' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}