<x-app-layout>
    <div class="dash-row-head">
        <div>
            <span class="badge badge-amber">Super Admin</span>
            <h1 class="mt-2">Payouts</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Transfer the amount to the host's account first, then mark it paid with the transfer reference.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.commissions.index') }}" class="btn btn-dark btn-sm">Commissions</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">Overview</a>
        </div>
    </div>

    <x-input-error :messages="$errors->get('amount')" />
    <x-input-error :messages="$errors->get('reference')" />

    <div class="table-wrap card">
        <table class="table">
            <thead><tr><th>#</th><th>Business</th><th>Amount</th><th>Destination</th><th>Status</th><th class="text-right">Action</th></tr></thead>
            <tbody>
                @forelse ($payouts as $payout)
                    <tr>
                        <td>{{ $payout->id }}<br><small style="color:var(--text-3)">{{ $payout->created_at->format('M j, Y') }}</small></td>
                        <td>{{ $payout->tenant?->name }}</td>
                        <td>PHP {{ number_format((float) $payout->amount, 2) }}</td>
                        <td>{{ ucfirst($payout->method) }} · {{ $payout->account_name }} · {{ $payout->account_number }}</td>
                        <td><span class="badge {{ $payout->badge() }}">{{ ucfirst($payout->status) }}</span>
                            @if ($payout->reference)<br><small style="color:var(--text-3)">Ref {{ $payout->reference }}</small>@endif</td>
                        <td class="text-right">
                            @if ($payout->status === 'requested')
                                <form method="POST" action="{{ route('admin.payouts.update', $payout->id) }}" class="flex gap-2 justify-end">
                                    @csrf
                                    @method('PATCH')
                                    <input name="reference" class="form-input" placeholder="Transfer ref" aria-label="Transfer reference" style="max-width:140px">
                                    <button name="status" value="paid" class="btn btn-sm btn-primary" type="submit">Mark paid</button>
                                    <button name="status" value="rejected" class="btn btn-sm btn-ghost" type="submit">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm" style="color:var(--text-3)">No payout requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payouts->links() }}</div>
</x-app-layout>
