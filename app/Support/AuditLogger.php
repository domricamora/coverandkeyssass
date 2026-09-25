<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Central audit logging service. Security-sensitive and
 * administrative actions must be recorded through this class.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $tenantId = null,
        ?int $userId = null,
    ): AuditLog {
        $entry = AuditLog::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenantId ?? app(TenantContext::class)->id(),
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => Request::ip(),
            'user_agent' => (string) Request::userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        // Phase 37: mirror to the `ops` log so bookings, orders, payments,
        // refunds, payouts, subscriptions and admin actions reach log tooling.
        // Who / where / what only — old and new values may hold personal data
        // and stay in the database.
        \Illuminate\Support\Facades\Log::channel('ops')->log(self::level($action), $action, array_filter([
            'audit' => $entry->uuid,
            'subject' => $entry->auditable_type ? $entry->auditable_type.'#'.$entry->auditable_id : null,
            'tenant_id' => $entry->tenant_id,
            'user_id' => $entry->user_id,
            'ip' => $entry->ip_address,
        ], fn ($v) => $v !== null));

        return $entry;
    }

    private static function level(string $action): string
    {
        return match (true) {
            (bool) preg_match('/failed|mismatch|suspend|reject|denied|inactive/', $action) => 'warning',
            (bool) preg_match('/refund|void|cancel|no_show|delete|destroy/', $action) => 'notice',
            default => 'info',
        };
    }
}
