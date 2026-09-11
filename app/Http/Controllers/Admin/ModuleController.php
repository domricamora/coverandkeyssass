<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Support\AuditLogger;
use App\Support\ModuleService;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private ModuleService $modules,
    ) {
    }

    public function index()
    {
        $modules = Module::query()
            ->withCount('tenantModules')
            ->ordered()
            ->paginate(25);

        return view('admin.modules.index', ['modules' => $modules]);
    }

    public function create()
    {
        return view('admin.modules.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:60', 'unique:modules,slug'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:60'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'is_core' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $module = Module::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'icon' => $validated['icon'] ?? null,
            'trial_days' => $validated['trial_days'] ?? 0,
            'is_core' => $validated['is_core'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => 'active',
        ]);

        $this->audit->log('platform.module.created', $module, null, ['name' => $module->name]);

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module created.'));
    }

    public function edit(Module $module)
    {
        return view('admin.modules.edit', ['module' => $module]);
    }

    public function update(Request $request, Module $module)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:60'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'is_core' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $old = $module->only(['name', 'status']);
        $module->update($validated);

        $this->audit->log('platform.module.updated', $module, $old, $validated);

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module updated.'));
    }

    public function destroy(Module $module)
    {
        if ($module->is_core) {
            return back()->with('error', __('Core modules cannot be deleted.'));
        }

        $old = ['name' => $module->name, 'slug' => $module->slug];
        $module->delete();

        $this->audit->log('platform.module.deleted', $module, $old, null);

        return redirect()->route('admin.modules.index')
            ->with('success', __('Module deleted.'));
    }
}