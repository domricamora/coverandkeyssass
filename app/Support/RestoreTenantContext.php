<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;

/**
 * Queue job middleware: run the job inside the tenant it was dispatched for.
 *
 * Queued models are restored without global scopes, but anything the job then
 * touches (e.g. a notification reading `$booking->property`) goes through the
 * deny-by-default tenant scope, so a worker with no context would see nothing.
 * The previous context is always put back (the sync queue runs jobs inside
 * the dispatching request).
 */
final class RestoreTenantContext
{
    public function __construct(private readonly ?int $tenantId) {}

    public function handle(object $job, Closure $next): mixed
    {
        $context = app(TenantContext::class);
        $previous = $context->snapshot();
        $tenant = $this->tenantId ? Tenant::query()->find($this->tenantId) : null;

        $tenant ? $context->set($tenant) : $context->forget();

        try {
            return $next($job);
        } finally {
            $context->restore($previous);
        }
    }
}
