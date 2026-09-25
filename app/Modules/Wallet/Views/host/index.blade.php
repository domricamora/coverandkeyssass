<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Wallet</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Online booking and food-order earnings after platform commission. Earnings unlock when the guest checks out or the order is completed.</p>
        </div>
    </div>

    <div class="stat-grid stat-grid--3">
        <div class="stat card">
            <p class="stat__label">Available to withdraw</p>
            <p class="stat__value">{{ $wallet->money($wallet->available_balance) }}</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Pending (stays not finished)</p>
            <p class="stat__value">{{ $wallet->money($wallet->pending_balance) }}</p>
        </div>
        <div class="stat card">
            <p class="stat__label">Payouts in progress</p>
            <p class="stat__value">{{ $wallet->money($payouts->where('status', 'requested')->sum('amount')) }}</p>
        </div>
    </div>

    @if (auth()->user()->hasPermissionTo('payouts.request'))
        <form method="POST" action="{{ route('wallet.payouts.store') }}" class="card p-6 mb-6 space-y-3" style="max-width:820px">
            @csrf
            <h2 class="text-lg">Request a payout</h2>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label for="w_amount" class="form-label">Amount</label>
                    <input id="w_amount" type="number" step="0.01" min="{{ $minPayout }}" name="amount" value="{{ old('amount') }}" required class="form-input" />
                </div>
                <div>
                    <label for="w_method" class="form-label">Method</label>
                    <select id="w_method" name="method" class="form-input">
                        @foreach (\App\Modules\Wallet\Models\Payout::METHODS as $method)
                            <option value="{{ $method }}" @selected(old('method') === $method)>{{ ucfirst($method) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="w_name" class="form-label">Account name</label>
                    <input id="w_name" name="account_name" value="{{ old('account_name') }}" required class="form-input" />
                </div>
                <div>
                    <label for="w_number" class="form-label">Account number</label>
                    <input id="w_number" name="account_number" value="{{ old('account_number') }}" required class="form-input" />
                </div>
            </div>
            <x-input-error :messages="$errors->get('amount')" />
            <div class="flex justify-end"><button type="submit" class="btn btn-primary">Request payout</button></div>
        </form>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <h2 class="text-lg">Recent commissions</h2>
            <table class="table mt-3">
                <thead><tr><th>Booking</th><th>Gross</th><th>Fee</th><th>You get</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($commissions as $commission)
                        <tr>
                            <td>{{ $commission->sourceLabel() }}</td>
                            <td>{{ number_format((float) $commission->gross, 2) }}</td>
                            <td>{{ number_format((float) $commission->platform_fee, 2) }} ({{ (float) $commission->rate }}%)</td>
                            <td>{{ number_format((float) $commission->host_amount, 2) }}</td>
                            <td>{{ ucfirst($commission->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-sm" style="color:var(--text-3)">No online payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card p-6">
            <h2 class="text-lg">Payouts</h2>
            <table class="table mt-3">
                <thead><tr><th>Requested</th><th>Amount</th><th>To</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($payouts as $payout)
                        <tr>
                            <td>{{ $payout->created_at->format('M j, Y') }}</td>
                            <td>{{ number_format((float) $payout->amount, 2) }}</td>
                            <td>{{ ucfirst($payout->method) }} ····{{ substr($payout->account_number, -4) }}</td>
                            <td><span class="badge {{ $payout->badge() }}">{{ ucfirst($payout->status) }}</span>
                                @if ($payout->reference)<br><small style="color:var(--text-3)">Ref {{ $payout->reference }}</small>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-sm" style="color:var(--text-3)">No payouts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-wrap card mt-6">
        <table class="table">
            <thead><tr><th>Date</th><th>Description</th><th>Balance</th><th class="text-right">Amount</th><th class="text-right">Balance after</th></tr></thead>
            <tbody>
                @forelse ($transactions as $tx)
                    <tr>
                        <td>{{ $tx->created_at->format('M j, Y H:i') }}</td>
                        <td>{{ $tx->description }}</td>
                        <td>{{ ucfirst($tx->bucket) }}</td>
                        <td class="text-right">{{ (float) $tx->amount >= 0 ? '+' : '−' }}{{ number_format(abs((float) $tx->amount), 2) }}</td>
                        <td class="text-right">{{ number_format((float) $tx->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No wallet activity yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</x-app-layout>
