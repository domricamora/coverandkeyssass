@php($money = fn ($v) => '₱'.number_format((float) $v, 2))
<x-app-layout>
    <div class="dash-row-head"><div><h1>Payables & receivables</h1></div></div>
    @include('accounting::partials.nav')
    <x-input-error :messages="$errors->get('amount')" />

    <div class="grid grid-cols-2 gap-4">
        <div class="card p-6">
            <h2 class="text-lg">We owe (suppliers)</h2>
            <table class="table mt-2">
                <tbody>
                    @forelse ($suppliers as $row)
                        <tr>
                            <td>{{ $row['supplier']->name }}</td>
                            <td class="text-right">{{ $money($row['owed']) }}</td>
                            <td class="text-right">
                                @if ($row['owed'] > 0 && auth()->user()->hasPermissionTo('accounting.manage'))
                                    <form method="POST" action="{{ route('accounting.suppliers.pay', $row['supplier']->id) }}" class="flex gap-1 justify-end">
                                        @csrf
                                        <input type="number" name="amount" step="0.01" min="0.01" max="{{ $row['owed'] }}" value="{{ $row['owed'] }}" class="form-input" style="width:110px" aria-label="Amount" />
                                        <select name="method" class="form-input" style="width:80px" aria-label="Method"><option value="bank">Bank</option><option value="cash">Cash</option></select>
                                        <input type="hidden" name="paid_on" value="{{ today()->toDateString() }}" />
                                        <button type="submit" class="btn btn-sm btn-primary">Pay</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="color:var(--text-3)">No suppliers.</td></tr>
                    @endforelse
                    @if ($otherPayables != 0.0)
                        <tr><td>Other payables (repairs, bills)</td><td class="text-right">{{ $money($otherPayables) }}</td><td></td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="card p-6">
            <h2 class="text-lg">Owed to us (open invoices)</h2>
            <table class="table mt-2">
                <thead><tr><th>Invoice</th><th>Due</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                    @forelse ($receivables as $invoice)
                        <tr>
                            <td><a href="{{ route('accounting.invoices.show', $invoice->id) }}">{{ $invoice->number }}</a> · {{ $invoice->customer_name }}</td>
                            <td>{{ $invoice->due_date->format('M j') }} @if ($invoice->isOverdue())<span class="badge badge-red">{{ (int) $invoice->due_date->diffInDays(today()) }}d overdue</span>@endif</td>
                            <td class="text-right">{{ $money($invoice->balance()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="color:var(--text-3)">Nothing outstanding.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
