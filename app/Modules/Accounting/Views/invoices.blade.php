<x-app-layout>
    <div class="dash-row-head"><div><h1>Invoices</h1><p class="mt-1 text-sm" style="color:var(--text-3)">Corporate accounts, events and anything billed on terms.</p></div></div>
    @include('accounting::partials.nav')
    <x-input-error :messages="$errors->get('lines')" />

    <div class="grid grid-cols-3 gap-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Due</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        <tr>
                            <td><a href="{{ route('accounting.invoices.show', $inv->id) }}"><strong>{{ $inv->number }}</strong></a></td>
                            <td>{{ $inv->customer_name }}</td>
                            <td>{{ $inv->due_date->format('M j') }}</td>
                            <td><span class="badge {{ $inv->status === 'paid' ? 'badge-green' : ($inv->isOverdue() ? 'badge-red' : 'badge-gray') }}">{{ $inv->isOverdue() ? 'Overdue' : ucfirst($inv->status) }}</span></td>
                            <td class="text-right">₱{{ number_format((float) $inv->total, 2) }}</td>
                            <td class="text-right">₱{{ number_format($inv->balance(), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-sm" style="color:var(--text-3)">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $invoices->links() }}</div>
        </div>
        @if (auth()->user()->hasPermissionTo('accounting.manage'))
            <form method="POST" action="{{ route('accounting.invoices.store') }}" class="card p-6 space-y-2" x-data="{ lines: [0] }">
                @csrf
                <h2 class="text-lg">New invoice</h2>
                <input name="customer_name" type="text" required class="form-input" placeholder="Customer" aria-label="Customer" />
                <input name="customer_email" type="email" class="form-input" placeholder="Email" aria-label="Customer email" />
                <div class="flex gap-2">
                    <input name="issue_date" type="date" value="{{ today()->toDateString() }}" required class="form-input" aria-label="Issue date" />
                    <input name="due_date" type="date" value="{{ today()->addDays(30)->toDateString() }}" required class="form-input" aria-label="Due date" />
                </div>
                <input name="tax_rate" type="number" step="0.01" min="0" max="50" value="12" class="form-input" aria-label="VAT %" />
                <template x-for="(line, i) in lines" :key="i">
                    <div class="flex gap-1">
                        <input :name="`lines[${i}][description]`" type="text" class="form-input" placeholder="Description" aria-label="Description" />
                        <input :name="`lines[${i}][quantity]`" type="number" step="0.01" min="0" value="1" class="form-input" style="width:70px" aria-label="Quantity" />
                        <input :name="`lines[${i}][unit_price]`" type="number" step="0.01" min="0" class="form-input" style="width:100px" placeholder="₱" aria-label="Unit price" />
                    </div>
                </template>
                <button type="button" class="btn btn-sm btn-ghost" @click="lines.push(lines.length)">+ Line</button>
                <button type="submit" class="btn btn-primary w-full">Save draft</button>
            </form>
        @endif
    </div>
</x-app-layout>
