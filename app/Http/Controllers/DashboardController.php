<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $tenant = app(TenantContext::class)->tenant();

        $members = $tenant->tenantUsers()
            ->with('user:id,name,email,status')
            ->orderBy('created_at')
            ->get();

        $properties = \App\Modules\Marketplace\Models\Property::query();
        $restaurants = \App\Modules\Marketplace\Models\Restaurant::query();
        $activeModules = app(\App\Support\ModuleService::class)->activeForTenant($tenant)
            ->filter(fn ($tm) => $tm->module && ! $tm->module->is_core && $tm->expires_at === null);

        // Onboarding steps, each ticked from real data.
        $steps = [
            ['done' => $members->count() > 1, 'label' => 'Invite your team and give each member a role', 'route' => 'team'],
            ['done' => $activeModules->isNotEmpty(), 'label' => 'Switch on the modules your business runs', 'route' => 'billing.index'],
            ['done' => (clone $properties)->exists() || (clone $restaurants)->exists(), 'label' => 'Add your first property or restaurant', 'route' => 'properties.index'],
            ['done' => (clone $properties)->where('status', 'published')->exists() || (clone $restaurants)->where('status', 'published')->exists(), 'label' => 'Publish a listing to the marketplace', 'route' => 'properties.index'],
        ];

        return view('dashboard', [
            'tenant' => $tenant,
            'members' => $members,
            'userRole' => $request->user()->tenantRole($tenant),
            'listings' => (clone $properties)->count() + (clone $restaurants)->count(),
            'activeModules' => $activeModules,
            'steps' => $steps,
        ]);
    }

    public function settings(Request $request)
    {
        $this->authorize('update', app(TenantContext::class)->tenant());

        return view('tenants.settings', [
            'tenant' => app(TenantContext::class)->tenant(),
        ]);
    }

    public function update(Request $request)
    {
        $tenant = app(TenantContext::class)->tenant();

        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $old = ['name' => $tenant->name];
        $tenant->update($validated);

        $this->audit->log('tenant.updated', $tenant, $old, ['name' => $tenant->name]);

        return back()->with('success', __('Business profile updated.'));
    }
}
