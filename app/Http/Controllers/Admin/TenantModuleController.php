<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Support\AuditLogger;
use App\Support\ModuleService;
use Illuminate\Http\Request;

class TenantModuleController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private ModuleService $modules,
    ) {
    }

    /**
     * Show the module management screen for a specific tenant.
     */
    public function edit(Tenant $tenant)
    {
        $allModules = Module::query()->active()->ordered()->get();

        $active = TenantModule::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->get()->keyBy('module_id');

        return \Inertia\Inertia::render('Admin/Tenants/Modules', [
            'tenant' => $tenant->name,
            'modules' => $allModules->map(fn (Module $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'description' => $m->description,
                'core' => (bool) $m->is_core,
                'active' => $active->has($m->id),
                'trialEnds' => $active->get($m->id)?->trialEndsAtDisplay(),
                'disable' => route('admin.tenants.modules.destroy', [$tenant, $m]),
            ]),
            'urls' => ['enable' => route('admin.tenants.modules.store', $tenant), 'back' => route('admin.tenants.show', $tenant)],
        ]);
    }

    /**
     * Enable a module for a tenant.
     */
    public function store(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'module_id' => ['required', 'exists:modules,id'],
        ]);

        $module = Module::query()->findOrFail($validated['module_id']);

        $this->modules->enableForTenant($module, $tenant);

        $this->audit->log('platform.module.activated', $module, null, [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);

        return back()->with('success', __('"' . $module->name . '" enabled for ' . $tenant->name . '.'));
    }

    /**
     * Disable a module for a tenant.
     */
    public function destroy(Tenant $tenant, Module $module)
    {
        $this->modules->disableForTenant($module, $tenant);

        $this->audit->log('platform.module.deactivated', $module, null, [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);

        return back()->with('success', __('"' . $module->name . '" disabled for ' . $tenant->name . '.'));
    }
}