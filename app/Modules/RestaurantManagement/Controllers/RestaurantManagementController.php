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

    /** Sub-navigation shared by the restaurant screens (React). */
    protected function tabs(Restaurant $restaurant, string $active): array
    {
        $user = request()->user();
        $slug = $restaurant->slug;

        return collect([
            ['show', 'Overview', route('restaurants.show', $slug), 'restaurants.view'],
            ['edit', 'Profile & photos', route('restaurants.edit', $slug), 'restaurants.update'],
            ['menu', 'Menu', route('restaurants.menu', $slug), 'menu.view'],
            ['tables', 'Tables', route('restaurants.tables', $slug), 'tables.view'],
            ['reservations', 'Reservations', route('restaurants.reservations', $slug), 'reservations.view'],
            ['orders', 'Orders', route('restaurants.orders.index', $slug), 'orders.view'],
            ['delivery', 'Delivery', route('restaurants.delivery.index', $slug), 'delivery.manage'],
        ])->filter(fn ($t) => $user->hasPermissionTo($t[3]))
            ->map(fn ($t) => ['label' => $t[1], 'href' => $t[2], 'active' => $t[0] === $active])->values()->all();
    }

    /** 403 unless the actor holds the permission inside the active tenant. */
    protected function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
