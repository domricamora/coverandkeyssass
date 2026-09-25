@php($canManage = auth()->user()->hasPermissionTo('loyalty.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $account->contact?->name }} <span class="badge badge-amber">{{ $account->tierLabel() }}</span></h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ number_format($account->points_balance) }} points · {{ number_format($account->lifetime_points) }} lifetime ·
                @if ($next = $account->nextTier()) {{ number_format($next[1]) }} to {{ $next[0] }} @else top tier @endif
                · referral code <code>{{ $account->referral_code }}</code>{{ $account->referrer ? ' · referred by '.$account->referrer->contact?->name : '' }}
            </p>
        </div>
        <div class="flex gap-2">
            @if ($account->contact)<a href="{{ route('crm.show', $account->crm_contact_id) }}" class="btn btn-ghost">Guest profile</a>@endif
            <a href="{{ route('loyalty.index') }}" class="btn btn-ghost">Loyalty</a>
        </div>
    </div>
    @foreach (['points', 'reward'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="table-wrap card" style="grid-column:span 2">
            <table class="table">
                <thead><tr><th>When</th><th>What</th><th class="text-right">Points</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                    @forelse ($transactions as $t)
                        <tr><td>{{ $t->created_at->format('M j, Y') }}</td><td>{{ $t->description }} <small style="color:var(--text-3)">{{ $t->user?->name }}</small></td><td class="text-right" style="color:{{ $t->points < 0 ? 'var(--danger, #b91c1c)' : 'inherit' }}">{{ $t->points > 0 ? '+' : '' }}{{ number_format($t->points) }}</td><td class="text-right">{{ number_format($t->balance_after) }}</td></tr>
                    @empty
                        <tr><td colspan="4" style="color:var(--text-3)">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="space-y-4">
            @if ($canManage)
                <form method="POST" action="{{ route('loyalty.members.redeem', $account->id) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Redeem a reward</h2>
                    <select name="reward_id" class="form-input" aria-label="Reward">
                        @foreach ($rewards as $r)<option value="{{ $r->id }}" @disabled($r->points_cost > $account->points_balance)>{{ $r->name }} ({{ number_format($r->points_cost) }} pts)</option>@endforeach
                    </select>
                    <button type="submit" class="btn btn-primary w-full">Redeem</button>
                </form>
                <form method="POST" action="{{ route('loyalty.members.adjust', $account->id) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Adjust points</h2>
                    <input name="points" type="number" required class="form-input" placeholder="+50 or -20" aria-label="Points" />
                    <input name="reason" type="text" required class="form-input" placeholder="Reason" aria-label="Reason" />
                    <button type="submit" class="btn btn-ghost w-full">Adjust</button>
                </form>
            @endif
            <div class="card p-6 text-sm">
                <h2 class="text-lg">Credit & gift cards</h2>
                <ul class="mt-2 space-y-1">
                    @forelse ($cards as $card)
                        <li><code>{{ $card->code }}</code> · {{ $card->kind }} · ₱{{ number_format((float) $card->balance, 2) }} left{{ $card->status === 'void' ? ' · void' : '' }}</li>
                    @empty
                        <li style="color:var(--text-3)">None.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
