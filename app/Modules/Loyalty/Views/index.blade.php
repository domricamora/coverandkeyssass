@php($canManage = auth()->user()->hasPermissionTo('loyalty.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Loyalty</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                Programme {{ $program->enabled ? 'on' : 'off' }} · ₱{{ number_format((float) $program->pesos_per_point, 0) }} spending = 1 point ·
                @foreach (\App\Modules\Loyalty\Models\LoyaltyAccount::TIERS as $key => [$needed, $label]){{ $label }} {{ $tierCounts[$key] ?? 0 }}{{ $loop->last ? '' : ' · ' }}@endforeach
                · gift cards & credit outstanding ₱{{ number_format($outstanding, 2) }}
            </p>
        </div>
        <form method="GET" class="flex gap-2"><input type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Member or referral code" aria-label="Search members" /><button class="btn btn-sm btn-ghost" type="submit">Search</button></form>
    </div>

    @foreach (['name', 'points_cost', 'promotion_id', 'credit_amount', 'amount', 'pesos_per_point'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4">
        <div class="space-y-4" style="grid-column:span 2">
            <div class="table-wrap card">
                <table class="table">
                    <thead><tr><th>Member</th><th>Tier</th><th class="text-right">Points</th><th class="text-right">Lifetime</th><th>Referral code</th></tr></thead>
                    <tbody>
                        @forelse ($members as $m)
                            <tr>
                                <td><a href="{{ route('loyalty.members.show', $m->id) }}"><strong>{{ $m->contact?->name }}</strong></a><br><small style="color:var(--text-3)">{{ $m->contact?->email }}</small></td>
                                <td><span class="badge badge-amber">{{ $m->tierLabel() }}</span></td>
                                <td class="text-right">{{ number_format($m->points_balance) }}</td>
                                <td class="text-right">{{ number_format($m->lifetime_points) }}</td>
                                <td><code>{{ $m->referral_code }}</code></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No members yet — guests join automatically when they earn.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $members->links() }}</div>
            </div>

            <div class="card p-6">
                <h2 class="text-lg">Gift cards & store credit</h2>
                <table class="table mt-2">
                    <tbody>
                        @forelse ($giftCards as $card)
                            <tr>
                                <td><code>{{ $card->code }}</code> <span class="badge badge-gray">{{ $card->kind }}</span></td>
                                <td>{{ $card->contact?->name ?? '—' }}</td>
                                <td class="text-right">₱{{ number_format((float) $card->balance, 2) }} / {{ number_format((float) $card->initial_value, 2) }}</td>
                                <td>{{ $card->status === 'void' ? 'void' : ($card->expires_on ? 'expires '.$card->expires_on->format('M j, Y') : '') }}</td>
                                <td class="text-right">
                                    @if ($canManage && $card->status === 'active')
                                        <form method="POST" action="{{ route('loyalty.gift-cards.void', $card->id) }}" onsubmit="return confirm('Void this card?')">@csrf<button class="btn btn-sm btn-ghost" type="submit">Void</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td style="color:var(--text-3)">No cards yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-4">
            @if ($canManage)
                <form method="POST" action="{{ route('loyalty.program') }}" class="card p-6 space-y-2">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-lg">Programme</h2>
                    <label class="text-sm flex items-center gap-1"><input type="checkbox" name="enabled" value="1" @checked($program->enabled) /> Guests earn points</label>
                    <label class="text-sm">₱ spending per point <input name="pesos_per_point" type="number" step="1" min="1" value="{{ (float) $program->pesos_per_point }}" class="form-input" /></label>
                    <label class="text-sm">Referral bonus (points, each) <input name="referral_points" type="number" min="0" value="{{ $program->referral_points }}" class="form-input" /></label>
                    <button type="submit" class="btn btn-primary w-full">Save</button>
                </form>
            @endif

            <div class="card p-6 text-sm">
                <h2 class="text-lg">Rewards</h2>
                <ul class="mt-2 space-y-1">
                    @forelse ($rewards as $r)
                        <li class="flex justify-between items-center">
                            <span>{{ $r->name }} · {{ number_format($r->points_cost) }} pts · {{ $r->kind === 'coupon' ? 'coupon '.$r->promotion?->code : '₱'.number_format((float) $r->credit_amount, 0).' credit' }} @unless ($r->is_active)<span class="badge badge-gray">retired</span>@endunless</span>
                            @if ($canManage)<form method="POST" action="{{ route('loyalty.rewards.toggle', $r->id) }}">@csrf<button class="btn btn-sm btn-ghost" type="submit">{{ $r->is_active ? 'Retire' : 'Restore' }}</button></form>@endif
                        </li>
                    @empty
                        <li style="color:var(--text-3)">No rewards yet.</li>
                    @endforelse
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ route('loyalty.rewards.store') }}" class="space-y-2 mt-3" x-data="{ kind: 'credit' }">
                        @csrf
                        <input name="name" type="text" required class="form-input" placeholder="₱500 dining credit" aria-label="Reward name" />
                        <div class="flex gap-2">
                            <input name="points_cost" type="number" min="1" required class="form-input" placeholder="Points" aria-label="Points cost" />
                            <select name="kind" x-model="kind" class="form-input" aria-label="Kind"><option value="credit">Store credit</option><option value="coupon">Coupon</option></select>
                        </div>
                        <input name="credit_amount" x-show="kind === 'credit'" type="number" step="0.01" min="1" class="form-input" placeholder="Credit ₱" aria-label="Credit amount" />
                        <select name="promotion_id" x-show="kind === 'coupon'" class="form-input" aria-label="Coupon promotion">
                            <option value="">Pick a promotion</option>
                            @foreach ($promotions as $p)<option value="{{ $p->id }}">{{ $p->code }} — {{ $p->label() }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-ghost">Add reward</button>
                    </form>
                @endif
            </div>

            @if ($canManage)
                <form method="POST" action="{{ route('loyalty.gift-cards.store') }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Sell a gift card</h2>
                    <input name="amount" type="number" step="0.01" min="1" required class="form-input" placeholder="Value ₱" aria-label="Value" />
                    <select name="paid_via" class="form-input" aria-label="Paid via"><option value="cash">Cash</option><option value="bank">Card / bank</option></select>
                    <select name="crm_contact_id" class="form-input" aria-label="For guest">
                        <option value="">Bearer (no guest)</option>
                        @foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->email ? ' · '.$c->email : '' }}</option>@endforeach
                    </select>
                    <input name="expires_on" type="date" class="form-input" aria-label="Expires on" />
                    <button type="submit" class="btn btn-primary w-full">Issue card</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
