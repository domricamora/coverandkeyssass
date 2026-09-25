<?php

namespace App\Modules\Billing\Support;

use App\Models\Module;
use App\Models\ModulePlan;
use App\Models\Tenant;
use App\Models\TenantModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan limits and live usage (Phase 27). A limit comes from the business's
 * own tenant_modules.limits (a Super Admin override), else the module's
 * Monthly plan `limits`; missing = unlimited. Usage is counted live.
 */
final class Usage
{
    /** metric => [label, owning module slug, table counted by tenant_id] */
    public const METRICS = [
        'properties' => ['Properties', 'property', 'properties'],
        'rooms' => ['Rooms', 'property', 'rooms'],
        'restaurants' => ['Restaurants', 'restaurant', 'restaurants'],
        'staff' => ['Team members', 'core', 'tenant_users'],
    ];

    public static function count(Tenant $tenant, string $metric): int
    {
        $query = DB::table(self::METRICS[$metric][2])->where('tenant_id', $tenant->id);

        return match ($metric) {
            'staff' => $query->where('status', 'active')->count(),
            default => $query->whereNull('deleted_at')->count(),
        };
    }

    public static function limit(Tenant $tenant, string $metric): ?int
    {
        $module = Module::query()->where('slug', self::METRICS[$metric][1])->first();
        if (! $module) {
            return null;
        }

        $override = TenantModule::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)->where('module_id', $module->id)->value('limits');
        $override = is_string($override) ? json_decode($override, true) : $override;

        $limit = $override[$metric]
            ?? ModulePlan::query()->where('module_id', $module->id)->where('billing_interval', 'monthly')->where('is_active', true)->value('limits')[$metric]
            ?? null;

        return $limit === null ? null : (int) $limit;
    }

    /** @return array<string, array{label: string, used: int, limit: ?int}> */
    public static function report(Tenant $tenant): array
    {
        return collect(self::METRICS)->map(fn ($meta, $metric) => [
            'label' => $meta[0],
            'used' => self::count($tenant, $metric),
            'limit' => self::limit($tenant, $metric),
        ])->all();
    }

    /** Refuse to add `$adding` more when it would pass the plan limit. */
    public static function ensureRoom(Tenant $tenant, string $metric, int $adding = 1, string $field = 'limit'): void
    {
        $limit = self::limit($tenant, $metric);

        if ($limit !== null && self::count($tenant, $metric) + $adding > $limit) {
            throw ValidationException::withMessages([
                $field => 'Your plan allows '.$limit.' '.strtolower(self::METRICS[$metric][0]).'. Contact us or upgrade on the Billing page to add more.',
            ]);
        }
    }
}
