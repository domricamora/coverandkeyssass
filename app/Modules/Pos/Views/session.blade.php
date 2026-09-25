@php($money = fn ($v) => '₱'.number_format((float) $v, 2))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Z-report — {{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                Opened {{ $session->opened_at->format('M j, g:i A') }} by {{ $session->opener?->name ?? '—' }}
                @if ($session->closed_at) · closed {{ $session->closed_at->format('M j, g:i A') }} by {{ $session->closer?->name ?? '—' }} @else · still open @endif
            </p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn btn-ghost" onclick="window.print()">Print</button>
            <a href="{{ route('pos.register', $restaurant) }}" class="btn btn-ghost">Register</a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mt-4">
        <div class="card p-6">
            <h2 class="text-lg">Takings</h2>
            <table class="table mt-2">
                <tbody>
                    @foreach ($report['by_method'] as $method => $amount)
                        <tr><td>{{ \Illuminate\Support\Str::headline($method) }}</td><td class="text-right">{{ $money($amount) }}</td></tr>
                    @endforeach
                    <tr><td>Refunds</td><td class="text-right">−{{ $money($report['refunds']) }}</td></tr>
                    <tr><td><strong>Net</strong></td><td class="text-right"><strong>{{ $money($report['net']) }}</strong></td></tr>
                    <tr><td>Tickets</td><td class="text-right">{{ $report['orders'] }}</td></tr>
                    <tr><td>Discounts given</td><td class="text-right">{{ $money($report['discounts']) }}</td></tr>
                    <tr><td>Tax (in totals)</td><td class="text-right">{{ $money($report['tax']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card p-6">
            <h2 class="text-lg">Cash drawer</h2>
            <table class="table mt-2">
                <tbody>
                    <tr><td>Opening float</td><td class="text-right">{{ $money($session->opening_float) }}</td></tr>
                    <tr><td>Expected cash</td><td class="text-right">{{ $money($report['cash_expected']) }}</td></tr>
                    @if ($session->closed_at)
                        <tr><td>Counted cash</td><td class="text-right">{{ $money($session->counted_cash) }}</td></tr>
                        <tr><td><strong>Variance</strong></td><td class="text-right"><strong style="color:{{ (float) $session->variance < 0 ? 'var(--danger, #b91c1c)' : 'inherit' }}">{{ $money($session->variance) }}</strong></td></tr>
                    @endif
                </tbody>
            </table>
            @if ($session->notes)<p class="text-sm mt-2" style="color:var(--text-3)">{{ $session->notes }}</p>@endif
        </div>
    </div>
</x-app-layout>
