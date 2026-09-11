<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces tenant isolation for tenant-owned tables.
 *
 * - When a tenant context is active, queries are scoped to it.
 * - When no context is active, tenant-owned rows are never visible
 *   (the query returns nothing instead of everything).
 * - On create, tenant_id is stamped from the context.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->id();

            $builder->when(
                $tenantId !== null,
                fn (Builder $q) => $q->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId),
                // Without context, deny access to tenant-owned data entirely.
                fn (Builder $q) => $q->whereRaw('1 = 0'),
            );
        });

        static::creating(function (Model $model): void {
            if (! $model->tenant_id) {
                $tenantId = app(TenantContext::class)->id();

                if ($tenantId === null) {
                    throw new \RuntimeException(
                        'Cannot create tenant-owned ['.class_basename($model).'] without an active tenant context.'
                    );
                }

                $model->tenant_id = $tenantId;
            }
        });
    }
}
