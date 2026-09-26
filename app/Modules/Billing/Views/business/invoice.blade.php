<x-app-layout>
    @php($money = fn (int $cents) => \App\Modules\Billing\Models\Invoice::money($cents))
    <div class="dash-row-head">
        <div>
            <h1>Invoice {{ $invoice->number }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $invoice->tenant->name }} · {{ $invoice->period_start->format('M j, Y') }} – {{ $invoice->period_end->format('M j, Y') }}</p>
        </div>
        <div class="flex gap-2">
            <a class="btn btn-sm btn-ghost" href="{{ route('billing.index') }}">Back to billing</a>
            <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    @foreach (['success', 'warning'] as $flash)
        @if (session($flash))<div class="card mt-4 p-4" role="status">{{ session($flash) }}</div>@endif
    @endforeach
    @if ($errors->any())<div class="card mt-4 p-4" role="alert" style="color:var(--danger, #b91c1c)">{{ $errors->first() }}</div>@endif

    <div class="card mt-6 p-6">
        <table class="dash-table" style="width:100%">
            <thead><tr><th scope="col">Item</th><th scope="col">Qty</th><th scope="col" style="text-align:right">Price</th><th scope="col" style="text-align:right">Amount</th></tr></thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td style="text-align:right">{{ $money($item->unit_cents) }}</td>
                        <td style="text-align:right">{{ $money($item->amount_cents) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="3" style="text-align:right">Subtotal</td><td style="text-align:right">{{ $money($invoice->subtotal_cents) }}</td></tr>
                @if ($invoice->discount_cents)
                    <tr><td colspan="3" style="text-align:right">Coupon {{ $invoice->coupon_code }}</td><td style="text-align:right">−{{ $money($invoice->discount_cents) }}</td></tr>
                @endif
                <tr><th colspan="3" scope="row" style="text-align:right">Total (VAT inclusive)</th><th style="text-align:right">{{ $money($invoice->total_cents) }}</th></tr>
            </tfoot>
        </table>

        <p class="mt-4">
            @if ($invoice->status === 'paid')
                <span class="badge badge-green">Paid</span> {{ $invoice->paid_at?->format('M j, Y') }}{{ $invoice->payment_method && $invoice->payment_method !== 'none' ? ' · '.$invoice->payment_method : '' }}
            @elseif ($invoice->status === 'void')
                <span class="badge">Void</span>
            @else
                <span class="badge {{ $invoice->isOverdue() ? 'badge-amber' : '' }}">{{ $invoice->isOverdue() ? 'Overdue' : 'Open' }}</span> Due {{ $invoice->due_at->format('M j, Y') }}
            @endif
        </p>

        @if ($invoice->status === 'open' && auth()->user()->hasPermissionTo('billing.manage'))
            @if (filled(config('services.paymongo.secret_key'))) {{-- billing invoices pay through PayMongo --}}
                <form method="POST" action="{{ route('billing.invoices.pay', $invoice->number) }}" class="mt-4">
                    @csrf
                    <button class="btn btn-primary" type="submit">Pay {{ $money($invoice->total_cents) }} with PayMongo</button>
                </form>
            @endif
            <p class="mt-2 text-sm" style="color:var(--text-3)">Paying by bank transfer? Put {{ $invoice->number }} in the reference; we confirm it within one business day.</p>
        @endif
    </div>
</x-app-layout>
