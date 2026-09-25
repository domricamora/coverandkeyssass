@php($canManage = auth()->user()->hasPermissionTo('accounting.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $invoice->number }} · {{ $invoice->customer_name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Issued {{ $invoice->issue_date->format('M j, Y') }} · due {{ $invoice->due_date->format('M j, Y') }} · <span class="badge badge-gray">{{ ucfirst($invoice->status) }}</span></p>
        </div>
        <div class="flex gap-2">
            @if ($canManage && $invoice->status === 'draft')
                <form method="POST" action="{{ route('accounting.invoices.issue', $invoice->id) }}">@csrf<button class="btn btn-primary" type="submit">Issue</button></form>
            @endif
            @if ($canManage && in_array($invoice->status, ['draft', 'issued'], true) && (float) $invoice->amount_paid == 0.0)
                <form method="POST" action="{{ route('accounting.invoices.void', $invoice->id) }}" onsubmit="return confirm('Void this invoice?')">@csrf<button class="btn btn-ghost" type="submit">Void</button></form>
            @endif
            <a href="{{ route('accounting.invoices') }}" class="btn btn-ghost">All invoices</a>
        </div>
    </div>
    @include('accounting::partials.nav')
    <x-input-error :messages="$errors->get('amount')" />
    <x-input-error :messages="$errors->get('status')" />

    <div class="grid grid-cols-3 gap-4">
        <div class="card p-6" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @foreach ($invoice->lines as $line)
                        <tr><td>{{ $line->description }}</td><td class="text-right">{{ (float) $line->quantity }}</td><td class="text-right">{{ number_format((float) $line->unit_price, 2) }}</td><td class="text-right">{{ number_format((float) $line->line_total, 2) }}</td></tr>
                    @endforeach
                    <tr><td colspan="3">Subtotal</td><td class="text-right">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                    <tr><td colspan="3">VAT {{ (float) $invoice->tax_rate }}%</td><td class="text-right">{{ number_format((float) $invoice->tax_total, 2) }}</td></tr>
                    <tr><td colspan="3"><strong>Total</strong></td><td class="text-right"><strong>₱{{ number_format((float) $invoice->total, 2) }}</strong></td></tr>
                    <tr><td colspan="3">Paid</td><td class="text-right">₱{{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
                    <tr><td colspan="3"><strong>Balance</strong></td><td class="text-right"><strong>₱{{ number_format($invoice->balance(), 2) }}</strong></td></tr>
                </tbody>
            </table>
        </div>
        <div class="space-y-4">
            @if ($canManage && $invoice->status === 'issued')
                <form method="POST" action="{{ route('accounting.invoices.pay', $invoice->id) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Record payment</h2>
                    <input name="amount" type="number" step="0.01" min="0.01" value="{{ $invoice->balance() }}" required class="form-input" aria-label="Amount" />
                    <select name="method" class="form-input" aria-label="Method"><option value="bank">Bank</option><option value="cash">Cash</option></select>
                    <input name="paid_on" type="date" value="{{ today()->toDateString() }}" required class="form-input" aria-label="Paid on" />
                    <input name="reference" type="text" class="form-input" placeholder="Reference" aria-label="Reference" />
                    <button type="submit" class="btn btn-primary w-full">Record</button>
                </form>
            @endif
            <div class="card p-6 text-sm">
                <h2 class="text-lg">Payments</h2>
                <ul class="mt-2 space-y-1">
                    @forelse ($invoice->payments as $p)
                        <li>{{ $p->paid_on->format('M j') }} · {{ ucfirst($p->method) }} · ₱{{ number_format((float) $p->amount, 2) }} {{ $p->reference }}</li>
                    @empty
                        <li style="color:var(--text-3)">None yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
