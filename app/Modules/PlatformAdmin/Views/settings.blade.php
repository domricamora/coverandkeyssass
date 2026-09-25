<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Platform settings'])

    <form method="POST" action="{{ route('admin.settings.update') }}" class="card mt-6 p-6" style="max-width:720px;display:grid;gap:14px">
        @csrf
        @method('PUT')
        @foreach (\App\Modules\PlatformAdmin\Models\Setting::KEYS as $key => [$label, $rules, $help])
            <label style="display:grid;gap:4px">
                <strong>{{ $label }}</strong>
                <input name="{{ $key }}" value="{{ old($key, $values[$key]) }}" class="form-input" />
                <small style="color:var(--text-3)">{{ $help }}</small>
            </label>
        @endforeach
        <button type="submit" class="btn btn-primary" style="justify-self:start">Save settings</button>
    </form>
</x-app-layout>
