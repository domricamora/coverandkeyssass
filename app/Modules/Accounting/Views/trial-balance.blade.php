<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Trial balance</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">As of {{ \Carbon\Carbon::parse($asOf)->format('M j, Y') }}</p>
        </div>
        <form method="GET" class="flex gap-2"><input type="date" name="as_of" value="{{ $asOf }}" class="form-input" aria-label="As of" /><button class="btn btn-sm btn-ghost" type="submit">Apply</button></form>
    </div>
    @include('accounting::partials.nav')

    <div class="table-wrap card">
        <table class="table">
            <thead><tr><th>Account</th><th>Type</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
            <tbody>
                @foreach ($tb['rows'] as $row)
                    <tr><td>{{ $row['code'] }} · {{ $row['name'] }}</td><td>{{ ucfirst($row['type']) }}</td><td class="text-right">{{ $row['debit'] ? number_format($row['debit'], 2) : '' }}</td><td class="text-right">{{ $row['credit'] ? number_format($row['credit'], 2) : '' }}</td></tr>
                @endforeach
                <tr><td colspan="2"><strong>Totals</strong> @if (abs($tb['debits'] - $tb['credits']) < 0.01)<span class="badge badge-green">balanced</span>@else<span class="badge badge-red">out of balance</span>@endif</td><td class="text-right"><strong>{{ number_format($tb['debits'], 2) }}</strong></td><td class="text-right"><strong>{{ number_format($tb['credits'], 2) }}</strong></td></tr>
            </tbody>
        </table>
    </div>
</x-app-layout>
