<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Holds the tenant the current request/session is operating on.
 *
 * Tenant isolation is enforced through this context:
 * - The BelongsToTenant scope filters queries by the active tenant.
 * - Missing context for tenant-owned resources is treated as
 *   "no access", never as "global access".
 */
class TenantContext
{
    protected ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }
}
