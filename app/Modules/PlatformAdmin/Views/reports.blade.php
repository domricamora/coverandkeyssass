<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Reports', 'sub' => 'CSV exports for any date range (by creation date).'])

    <div class="grid gap-4 mt-6 sm:grid-cols-2">
        @foreach ($reports as $key => $label)
            <form method="GET" action="{{ route('admin.reports.export', $key) }}" class="card p-5" style="display:grid;gap:8px">
                <strong>{{ $label }}</strong>
                <div class="flex gap-2">
                    <input type="date" name="from" required class="form-input" value="{{ now()->startOfMonth()->toDateString() }}" aria-label="{{ $label }} from" />
                    <input type="date" name="to" required class="form-input" value="{{ now()->toDateString() }}" aria-label="{{ $label }} to" />
                </div>
                <button type="submit" class="btn btn-dark btn-sm" style="justify-self:start">Download CSV</button>
            </form>
        @endforeach
    </div>
</x-app-layout>
