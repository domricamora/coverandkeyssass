<?php

namespace App\Modules\RestaurantManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Restaurant;
use App\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Shared resolution + guard for host-side restaurant resources.
 *
 * Same rule as PropertyManagementController: routes take raw parameters
 * (SubstituteBindings runs before tenant.context) and resolve them here
 * through tenant-scoped relations — foreign rows are a plain 404.
 */
abstract class RestaurantManagementController extends Controller
{
    protected function resolveRestaurant(string $slug): Restaurant
    {
        $restaurant = Restaurant::query()->where('slug', $slug)->firstOrFail();

        $tenantId = app(TenantContext::class)->id();

        abort_unless($tenantId !== null && (int) $restaurant->tenant_id === (int) $tenantId, 404);

        return $restaurant;
    }

    /** 403 unless the actor holds the permission inside the active tenant. */
    protected function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
