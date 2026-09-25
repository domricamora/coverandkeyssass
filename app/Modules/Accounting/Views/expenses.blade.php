<x-app-layout>
    <div class="dash-row-head"><div><h1>Expenses</h1><p class="mt-1 text-sm" style="color:var(--text-3)">Utilities, rent, fuel and other bills outside purchasing.</p></div></div>
    @include('accounting::partials.nav')
    @foreach (['ledger_account_id', 'tax_amount', 'amount', 'vendor'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Date</th><th>Vendor</th><th>Account</th><th>Paid from</th><th class="text-right">VAT</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @forelse ($expenses as $e)
                        <tr><td>{{ $e->expense_date->format('M j') }}</td><td>{{ $e->vendor }}@if ($e->reference)<br><small style="color:var(--text-3)">{{ $e->reference }}</small>@endif</td><td>{{ $e->account?->name }}</td><td>{{ ucfirst($e->paid_from) }}</td><td class="text-right">{{ number_format((float) $e->tax_amount, 2) }}</td><td class="text-right">₱{{ number_format((float) $e->amount, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-sm" style="color:var(--text-3)">No expenses yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $expenses->links() }}</div>
        </div>
        @if (auth()->user()->hasPermissionTo('accounting.manage'))
            <form method="POST" action="{{ route('accounting.expenses.store') }}" class="card p-6 space-y-2">
                @csrf
                <h2 class="text-lg">Book an expense</h2>
                <input name="vendor" type="text" required class="form-input" placeholder="Meralco" aria-label="Vendor" />
                <select name="ledger_account_id" class="form-input" aria-label="Account">
                    @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach
                </select>
                <input name="expense_date" type="date" value="{{ today()->toDateString() }}" required class="form-input" aria-label="Date" />
                <div class="flex gap-2">
                    <input name="amount" type="number" step="0.01" min="0.01" required class="form-input" placeholder="Gross ₱" aria-label="Gross amount" />
                    <input name="tax_amount" type="number" step="0.01" min="0" class="form-input" placeholder="VAT ₱" aria-label="VAT" />
                </div>
                <select name="paid_from" class="form-input" aria-label="Paid from"><option value="bank">Bank</option><option value="cash">Cash</option><option value="payable">Not yet paid (payable)</option></select>
                <input name="reference" type="text" class="form-input" placeholder="OR / invoice no." aria-label="Reference" />
                <button type="submit" class="btn btn-primary w-full">Book</button>
            </form>
        @endif
    </div>
</x-app-layout>
