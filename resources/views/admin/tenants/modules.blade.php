<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">{{ $tenant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Manage active modules for this tenant.</p>
        </div>
        <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-ghost">Back to tenant</a>
    </div>

    <div class="card mt-6 p-6">
        <h2 class="dash-h2" style="margin-top:0">Available modules</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($modules as $module)
                @php($isActive = in_array($module->id, $activeIds))
                <div class="rounded-lg border p-4 {{ $isActive ? 'border-green-200 bg-green-50' : '' }}" style="border-color: {{ $isActive ? 'var(--green)' : 'var(--border)' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <strong style="color:var(--text)">{{ $module->name }}</strong>
                            @if ($module->is_core)
                                <span class="badge badge-blue ml-1">Core</span>
                            @endif
                            <p class="mt-1 text-xs" style="color:var(--text-3)">{{ $module->description }}</p>
                        </div>
                        @if ($isActive)
                            <span class="badge badge-green">Active</span>
                        @endif
                    </div>
                    <div class="mt-3">
                        @if ($isActive)
                            @php($tenantModule = $tenant->modules()->withoutGlobalScopes()->where('module_id', $module->id)->first())
                            @if ($tenantModule && $tenantModule->trialEndsAtDisplay())
                                <div class="text-xs mt-2 text-center" style="color:var(--text-3)">
                                    Trial ends {{ $tenantModule->trialEndsAtDisplay() }}
                                </div>
                            @endif
                            <form method="POST" action="{{ route('admin.tenants.modules.destroy', [$tenant, $module]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Disable</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.tenants.modules.store', $tenant) }}">
                                @csrf
                                <input type="hidden" name="module_id" value="{{ $module->id }}">
                                <button type="submit" class="btn btn-sm btn-primary">Enable</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-sm col-span-full" style="color:var(--text-3)">No modules defined yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>