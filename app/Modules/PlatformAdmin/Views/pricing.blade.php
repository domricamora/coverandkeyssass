<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Module pricing', 'sub' => 'Per-module plan prices (VAT-inclusive, ₱). Per-business overrides live on each business\'s modules page.'])

    <form method="POST" action="{{ route('admin.pricing.update') }}" class="card mt-6 p-6">
        @csrf
        @method('PUT')
        <table class="table">
            <thead><tr><th scope="col">Module</th><th scope="col">Plan</th><th scope="col">Price (₱)</th><th scope="col">Limits</th><th scope="col">Active</th></tr></thead>
            <tbody>
                @foreach ($plans as $plan)
                    <tr>
                        <td>{{ $plan->module->name }}</td>
                        <td>{{ $plan->name }}</td>
                        <td><input type="number" step="0.01" min="0" name="plans[{{ $plan->id }}][price]" value="{{ number_format($plan->price_cents / 100, 2, '.', '') }}" class="form-input" style="max-width:140px" aria-label="{{ $plan->module->name }} {{ $plan->name }} price" /></td>
                        <td><small>{{ $plan->limits ? collect($plan->limits)->map(fn ($v, $k) => $k.': '.$v)->implode(', ') : '—' }}</small></td>
                        <td><input type="checkbox" name="plans[{{ $plan->id }}][is_active]" value="1" @checked($plan->is_active) aria-label="{{ $plan->module->name }} {{ $plan->name }} active" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <button type="submit" class="btn btn-primary mt-4">Save pricing</button>
    </form>
</x-app-layout>
