<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Create module</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Add a new module that can be activated for tenants.</p>
        </div>
    </div>

    <div class="card mt-6 p-6" style="max-width:640px">
        <form method="POST" action="{{ route('admin.modules.store') }}" class="space-y-4">
            @csrf
            <div class="form-grid-2">
                <div>
                    <label for="name" class="form-label">Module name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required class="form-input" placeholder="e.g. Property Management" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <label for="slug" class="form-label">Slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required class="form-input" placeholder="property-management" />
                    <x-input-error :messages="$errors->get('slug')" />
                </div>
            </div>

            <div>
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="3" class="form-input" placeholder="What this module does…">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <div class="form-grid-2">
                <div>
                    <label for="category" class="form-label">Category</label>
                    <input id="category" name="category" type="text" value="{{ old('category', 'general') }}" required class="form-input" placeholder="e.g. operations, finance" />
                    <x-input-error :messages="$errors->get('category')" />
                </div>
                <div>
                    <label for="icon" class="form-label">Icon (optional)</label>
                    <input id="icon" name="icon" type="text" value="{{ old('icon') }}" class="form-input" placeholder="e.g. building-2" />
                    <x-input-error :messages="$errors->get('icon')" />
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label for="trial_days" class="form-label">Trial days</label>
                    <input id="trial_days" name="trial_days" type="number" min="0" value="{{ old('trial_days', 0) }}" class="form-input" />
                    <x-input-error :messages="$errors->get('trial_days')" />
                </div>
                <div>
                    <label for="sort_order" class="form-label">Sort order</label>
                    <input id="sort_order" name="sort_order" type="number" value="{{ old('sort_order', 0) }}" class="form-input" />
                    <x-input-error :messages="$errors->get('sort_order')" />
                </div>
            </div>

            <label class="check check--lg">
                <input type="checkbox" name="is_core" value="1" @checked(old('is_core'))>
                <span>Core module (cannot be deleted, auto-enabled for all tenants)</span>
            </label>

            <div class="flex justify-end gap-2 border-t pt-4" style="border-color:var(--border)">
                <a href="{{ route('admin.modules.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Create module</button>
            </div>
        </form>
    </div>
</x-app-layout>