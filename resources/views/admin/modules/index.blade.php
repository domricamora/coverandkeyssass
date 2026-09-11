<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">Modules</h1>
        </div>
        <a href="{{ route('admin.modules.create') }}" class="btn btn-primary">+ New module</a>
    </div>

    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Trial</th>
                    <th>Tenants</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($modules as $module)
                    <tr>
                        <td>
                            <strong style="color:var(--text)">{{ $module->name }}</strong>
                            <br><small style="color:var(--text-3)">{{ $module->slug }}</small>
                        </td>
                        <td style="color:var(--text-2)">{{ ucfirst($module->category) }}</td>
                        <td>
                            <span class="badge {{ $module->status === 'active' ? 'badge-green' : 'badge-gray' }}">
                                {{ ucfirst($module->status) }}
                            </span>
                            @if ($module->is_core)
                                <span class="badge badge-blue">Core</span>
                            @endif
                        </td>
                        <td style="color:var(--text-2)">
                            {{ $module->trial_days > 0 ? $module->trial_days . ' days' : '—' }}
                        </td>
                        <td style="color:var(--text-2)">{{ $module->tenant_modules_count }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.modules.edit', $module) }}" class="btn btn-sm btn-dark">Edit</a>
                            @if (! $module->is_core)
                                <form method="POST" action="{{ route('admin.modules.destroy', $module) }}" class="inline-form" onsubmit="return confirm('Delete this module?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm" style="color:var(--text-3)">No modules yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $modules->links() }}</div>
</x-app-layout>