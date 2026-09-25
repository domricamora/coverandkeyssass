<x-app-layout>
    @php($money = fn (int $cents) => \App\Modules\Billing\Models\Invoice::money($cents))
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">Billing</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $subscriptions->where('status', '!=', 'cancelled')->count() }} live subscriptions · MRR {{ $money($mrrCents) }}</p>
        </div>
    </div>

    @if (session('success'))<div class="card mt-4 p-4" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="card mt-4 p-4" role="alert" style="color:var(--danger, #b91c1c)">{{ $errors->first() }}</div>@endif

    <div class="card mt-6 p-6">
        <div class="flex flex-wrap justify-between gap-2">
            <h2 class="dash-h2" style="margin-top:0">Invoices</h2>
            <div class="flex gap-2">
                @foreach (['open' => 'Open', 'paid' => 'Paid', 'void' => 'Void', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('admin.billing.index', ['status' => $key]) }}" class="btn btn-sm {{ request('status', 'open') === $key ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <table class="dash-table mt-3" style="width:100%">
            <thead><tr><th scope="col">Invoice</th><th scope="col">Business</th><th scope="col">Total</th><th scope="col">Due</th><th scope="col">Status</th><th scope="col"></th></tr></thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->number }}</td>
                        <td>{{ $invoice->tenant->name }}</td>
                        <td>{{ $money($invoice->total_cents) }}</td>
                        <td>{{ $invoice->due_at->format('M j, Y') }}</td>
                        <td>{{ $invoice->isOverdue() ? 'Overdue' : ucfirst($invoice->status) }}{{ $invoice->payment_reference ? ' · '.$invoice->payment_reference : '' }}</td>
                        <td>
                            @if ($invoice->status === 'open')
                                <form method="POST" action="{{ route('admin.billing.invoices.paid', $invoice) }}" class="flex gap-1">
                                    @csrf
                                    <input name="reference" class="form-input" placeholder="Bank reference" aria-label="Payment reference for {{ $invoice->number }}" required style="max-width:160px" />
                                    <button class="btn btn-sm btn-dark" type="submit">Mark paid</button>
                                </form>
                                <form method="POST" action="{{ route('admin.billing.invoices.void', $invoice) }}" class="mt-1">
                                    @csrf
                                    <button class="btn btn-sm btn-ghost" type="submit" onclick="return confirm('Void {{ $invoice->number }}?')">Void</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="color:var(--text-3)">No invoices.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $invoices->links() }}
    </div>

    <div class="card mt-6 p-6">
        <h2 class="dash-h2" style="margin-top:0">Subscriptions</h2>
        <table class="dash-table mt-3" style="width:100%">
            <thead><tr><th scope="col">Business</th><th scope="col">Modules</th><th scope="col">Interval</th><th scope="col">Period ends</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td><a href="{{ route('admin.tenants.show', $subscription->tenant) }}">{{ $subscription->tenant->name }}</a></td>
                        <td>{{ $subscription->items->map(fn ($i) => $i->module->name)->implode(', ') }}</td>
                        <td>{{ ucfirst($subscription->billing_interval) }}</td>
                        <td>{{ $subscription->current_period_end->format('M j, Y') }}{{ $subscription->cancel_at_period_end ? ' (cancels)' : '' }}</td>
                        <td>{{ str_replace('_', ' ', ucfirst($subscription->status)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="color:var(--text-3)">No subscriptions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card mt-6 p-6">
        <h2 class="dash-h2" style="margin-top:0">Coupons</h2>
        <form method="POST" action="{{ route('admin.billing.coupons.store') }}" class="mt-3 grid gap-2 sm:grid-cols-4">
            @csrf
            <input name="code" class="form-input" placeholder="CODE" aria-label="Code" required value="{{ old('code') }}" />
            <input name="name" class="form-input" placeholder="Name" aria-label="Name" required value="{{ old('name') }}" />
            <input name="percent_off" type="number" min="1" max="100" class="form-input" placeholder="% off" aria-label="Percent off" value="{{ old('percent_off') }}" />
            <input name="amount_off" type="number" min="1" step="0.01" class="form-input" placeholder="or ₱ off" aria-label="Amount off" value="{{ old('amount_off') }}" />
            <select name="duration" class="form-input" aria-label="Duration">
                @foreach (\App\Modules\Billing\Models\Coupon::DURATIONS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            <input name="duration_cycles" type="number" min="1" max="36" class="form-input" placeholder="Invoices (repeating)" aria-label="Number of invoices" />
            <input name="max_redemptions" type="number" min="1" class="form-input" placeholder="Max businesses" aria-label="Maximum redemptions" />
            <input name="expires_at" type="date" class="form-input" aria-label="Expires on" />
            <button class="btn btn-primary" type="submit">Create coupon</button>
        </form>
        <table class="dash-table mt-4" style="width:100%">
            <tbody>
                @foreach ($coupons as $coupon)
                    <tr>
                        <td><strong>{{ $coupon->code }}</strong> {{ $coupon->name }}</td>
                        <td>{{ $coupon->label() }}</td>
                        <td>{{ $coupon->redeemed_count }}{{ $coupon->max_redemptions ? ' / '.$coupon->max_redemptions : '' }} used{{ $coupon->expires_at ? ' · expires '.$coupon->expires_at->format('M j, Y') : '' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.billing.coupons.toggle', $coupon) }}">
                                @csrf
                                <button class="btn btn-sm btn-ghost" type="submit">{{ $coupon->is_active ? 'Disable' : 'Enable' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
