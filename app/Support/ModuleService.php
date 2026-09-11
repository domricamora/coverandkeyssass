<?php

namespace App\Support;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Support\Facades\DB;

class ModuleService
{
    /**
     * Enable a module for a tenant. Auto-enables dependencies.
     */
    public function enableForTenant(Module $module, Tenant $tenant, array $overrides = []): TenantModule
    {
        return DB::transaction(function () use ($module, $tenant, $overrides) {
            // Auto-enable dependencies first, skipping core modules (always available).
            foreach ($this->dependenciesOf($module) as $dep) {
                if ($dep->is_core) {
                    continue;
                }

                $existing = TenantModule::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('module_id', $dep->id)
                    ->first();

                if (! $existing) {
                    $this->enableForTenant($dep, $tenant);
                }
            }

            $trialDays = $overrides['trial_days'] ?? $module->trial_days;

            // Set the tenant context so the BelongsToTenant scope can stamp tenant_id.
            $context = app(TenantContext::class);
            $prior = $context->snapshot();
            $context->set($tenant);

            try {
                $tenantModule = TenantModule::query()
                    ->withoutGlobalScopes()
                    ->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'module_id' => $module->id],
                        [
                            'status' => 'active',
                            'activated_at' => now(),
                            'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
                            'limits' => $overrides['limits'] ?? $module->default_limits,
                            'price_cents' => $overrides['price_cents'] ?? null,
                        ],
                    );
            } finally {
                $context->restore($prior);
            }

            return $tenantModule;
        });
    }

    /**
     * Disable a module for a tenant. Blocks if other active modules depend on it.
     */
    public function disableForTenant(Module $module, Tenant $tenant): void
    {
        $dependents = $this->activeDependents($module, $tenant);

        if ($dependents->isNotEmpty()) {
            throw new \RuntimeException(
                'Cannot disable "' . $module->name . '": required by ' .
                $dependents->map(fn ($d) => $d->name)->implode(', ')
            );
        }

        TenantModule::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('module_id', $module->id)
            ->update(['status' => 'disabled']);
    }

    /**
     * Get the modules a given module depends on.
     */
    public function dependenciesOf(Module $module): \Illuminate\Database\Eloquent\Collection
    {
        $deps = $module->metadata['dependencies'] ?? [];

        if (empty($deps)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return Module::query()
            ->whereIn('slug', $deps)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get active modules that depend on the given module.
     */
    public function activeDependents(Module $module, Tenant $tenant)
    {
        $all = Module::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn ($m) => in_array($module->slug, $m->metadata['dependencies'] ?? []));

        $enabledIds = TenantModule::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->pluck('module_id');

        return $all->whereIn('id', $enabledIds);
    }

    /**
     * Check whether a tenant has a module active (or in trial).
     */
    public function isEnabled(Module $module, Tenant $tenant): bool
    {
        return TenantModule::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('module_id', $module->id)
            ->where('status', 'active')
            ->whereNull('expires_at')
            ->exists();
    }

    /**
     * Get all active modules for a tenant.
     */
    public function activeForTenant(Tenant $tenant)
    {
        return TenantModule::query()
            ->with('module')
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get();
    }
}