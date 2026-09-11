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
        return AuditLog::create([
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
    }
}
