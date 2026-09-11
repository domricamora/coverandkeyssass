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

        return view('dashboard', [
            'tenant' => $tenant,
            'members' => $members,
            'userRole' => $request->user()->tenantRole($tenant),
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
