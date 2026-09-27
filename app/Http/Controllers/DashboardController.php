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

        // Owner overview (React, dashboard rebuild phase 4): KPIs, trends, today, portfolio.
        $user = $request->user();
        $range = in_array((int) $request->query('range'), [7, 30, 90], true) ? (int) $request->query('range') : 30;
        $to = \Carbon\CarbonImmutable::today();
        $from = $to->subDays($range - 1);
        $insights = new \App\Support\OwnerInsights([$tenant->id]);
        $money = $user->hasPermissionTo('accounting.view');

        $businesses = $user->tenants()->wherePivot('status', 'active')->where('tenants.status', 'active')->orderBy('name')->get(['tenants.id', 'tenants.name', 'tenants.slug', 'tenants.business_type']);

        return \Inertia\Inertia::render('Overview/Index', [
            'company' => ['name' => $tenant->name, 'type' => \Illuminate\Support\Str::headline((string) $tenant->business_type), 'role' => $user->tenantRole($tenant)?->display_name],
            'range' => $range,
            'money' => $money,
            'steps' => collect($steps)->contains('done', false) ? collect($steps)->map(fn ($s) => ['done' => $s['done'], 'label' => $s['label'], 'href' => route($s['route'])]) : null,
            'today' => $insights->today(),
            'kpis' => $money ? $insights->kpis($from, $to) : null,
            'previous' => $money ? $insights->kpis($from->subDays($range), $from->subDay()) : null,
            'daily' => $money ? $insights->daily($from, $to) : [],
            'channels' => $insights->channels($from, $to),
            'dishes' => $money ? $insights->topDishes($from, $to) : [],
            'team' => ['members' => $members->count(), 'listings' => (clone $properties)->count() + (clone $restaurants)->count(), 'modules' => $activeModules->count()],
            'portfolio' => $businesses->count() > 1 && $money ? $businesses->map(function ($b) use ($tenant, $to) {
                $one = new \App\Support\OwnerInsights([$b->id]);
                $k = $one->kpis($to->subDays(29), $to);

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'type' => \Illuminate\Support\Str::headline((string) $b->business_type),
                    'current' => $b->id === $tenant->id,
                    'occupancy' => $k['occupancy'],
                    'revenue' => $k['revenue'],
                    'adr' => $k['adr'],
                    'in_house' => $one->today()['in_house'],
                    'switch' => route('tenants.switch', $b->id),
                ];
            })->values() : [],
            'urls' => ['self' => route('dashboard'), 'frontdesk' => route('frontdesk.index'), 'accounting' => route('accounting.index')],
        ]);
    }

    public function settings(Request $request)
    {
        $this->authorize('update', app(TenantContext::class)->tenant());

        $tenant = app(TenantContext::class)->tenant();

        return \Inertia\Inertia::render('Settings/Index', [
            'company' => ['name' => $tenant->name, 'slug' => $tenant->slug, 'type' => \Illuminate\Support\Str::headline((string) $tenant->business_type), 'status' => $tenant->status, 'currency_symbol' => $tenant->settings['currency_symbol'] ?? ''],
            'platformSymbol' => \App\Support\Currency::platform(),
            'shortcuts' => ['team' => route('team'), 'billing' => route('billing.index'), 'notifications' => route('account.notification-settings'), 'profile' => route('profile.edit')],
            'urls' => ['update' => route('tenants.update')],
        ]);
    }

    public function update(Request $request)
    {
        $tenant = app(TenantContext::class)->tenant();

        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'currency_symbol' => ['nullable', 'string', 'max:4'], // empty = the platform default
        ]);

        $old = ['name' => $tenant->name, 'currency_symbol' => $tenant->settings['currency_symbol'] ?? null];
        $tenant->update([
            'name' => $validated['name'],
            'settings' => array_merge($tenant->settings ?? [], ['currency_symbol' => $validated['currency_symbol'] ?? null]),
        ]);

        $this->audit->log('tenant.updated', $tenant, $old, ['name' => $tenant->name, 'currency_symbol' => $validated['currency_symbol'] ?? null]);

        return back()->with('success', __('Business profile updated.'));
    }
}
