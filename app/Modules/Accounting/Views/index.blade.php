@php($money = fn ($v) => '₱'.number_format((float) $v, 2))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Accounting</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Profit & loss for {{ \Carbon\Carbon::parse($from)->format('M j') }} – {{ \Carbon\Carbon::parse($to)->format('M j, Y') }}</p>
        </div>
        <form method="GET" class="flex gap-2 items-center">
            <input type="date" name="from" value="{{ $from }}" class="form-input" aria-label="From" />
            <input type="date" name="to" value="{{ $to }}" class="form-input" aria-label="To" />
            <button type="submit" class="btn btn-sm btn-ghost">Apply</button>
        </form>
    </div>
    @include('accounting::partials.nav')

    <div class="grid grid-cols-4 gap-4">
        @foreach ([['Cash on hand', $cash], ['Bank', $bank], ['Platform wallet', $platformWallet], ['Guest folios open', $guestReceivables]] as [$label, $value])
            <div class="card p-4"><p class="text-sm" style="color:var(--text-3)">{{ $label }}</p><h2>{{ $money($value) }}</h2></div>
        @endforeach
    </div>

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="card p-6" style="grid-column:span 2">
            <h2 class="text-lg">Profit & loss</h2>
            <table class="table mt-2">
                <tbody>
                    <tr><td colspan="2"><strong>Revenue</strong></td></tr>
                    @forelse ($pnl['revenue'] as $row)
                        <tr><td>{{ $row['code'] }} · {{ $row['name'] }}</td><td class="text-right">{{ $money($row['amount']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" style="color:var(--text-3)">No revenue in this period.</td></tr>
                    @endforelse
                    <tr><td><strong>Total revenue</strong></td><td class="text-right"><strong>{{ $money($pnl['total_revenue']) }}</strong></td></tr>
                    <tr><td colspan="2"><strong>Expenses</strong></td></tr>
                    @foreach ($pnl['expenses'] as $row)
                        <tr><td>{{ $row['code'] }} · {{ $row['name'] }}</td><td class="text-right">{{ $money($row['amount']) }}</td></tr>
                    @endforeach
                    <tr><td><strong>Total expenses</strong></td><td class="text-right"><strong>{{ $money($pnl['total_expenses']) }}</strong></td></tr>
                    <tr><td><strong>Net {{ $pnl['net'] < 0 ? 'loss' : 'profit' }}</strong></td><td class="text-right"><strong style="color:{{ $pnl['net'] < 0 ? 'var(--danger, #b91c1c)' : 'inherit' }}">{{ $money($pnl['net']) }}</strong></td></tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            <div class="card p-6 text-sm">
                <h2 class="text-lg">VAT</h2>
                <p class="mt-2">Output VAT {{ $money($vat['output']) }}</p>
                <p>Input VAT −{{ $money($vat['input']) }}</p>
                <p><strong>{{ $vat['payable'] >= 0 ? 'Payable' : 'Refundable' }} {{ $money(abs($vat['payable'])) }}</strong></p>
            </div>
            <div class="card p-6 text-sm">
                <h2 class="text-lg">Receivables & payables</h2>
                <p class="mt-2">Invoices open {{ $money($receivables) }} @if ($overdue)<span class="badge badge-red">{{ $overdue }} overdue</span>@endif</p>
                <p>Accounts payable {{ $money($payables) }}</p>
                <a class="btn btn-sm btn-ghost mt-2" href="{{ route('accounting.payables') }}">Details</a>
            </div>
            <div class="card p-6 text-sm">
                <h2 class="text-lg">Commissions & payouts</h2>
                <p class="mt-2">Platform commissions {{ $money($commissions) }}</p>
                <p>Payouts to bank {{ $money($payouts) }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
