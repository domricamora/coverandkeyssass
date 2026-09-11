<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'description', 'category', 'icon', 'status',
        'is_core', 'trial_days', 'sort_order', 'default_limits', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
            'default_limits' => 'array',
            'metadata' => 'array',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(ModuleFeature::class)->orderBy('sort_order');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ModulePlan::class);
    }

    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    public function activePlans()
    {
        return $this->plans()->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}