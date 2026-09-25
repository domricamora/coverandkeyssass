<x-public-layout title="My rewards" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Rewards</h1>
        </div>

        @include('customer::partials.nav')
        @error('code')<p role="alert" class="card" style="padding:12px;color:var(--danger, #b91c1c);">{{ $message }}</p>@enderror

        @forelse ($accounts as $account)
            <div class="card" style="padding:16px;margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
                    <div>
                        <strong>{{ $account->business?->name }}</strong> <span class="chip-inline">{{ $account->tierLabel() }}</span>
                        <p class="muted" style="margin:4px 0 0;">{{ number_format($account->points_balance) }} points
                            @if ($next = $account->nextTier()) · {{ number_format($next[1]) }} more to {{ $next[0] }} @endif</p>
                        <p class="muted" style="margin:4px 0 0;">Share your code <strong>{{ $account->referral_code }}</strong> — you both get a bonus.</p>
                    </div>
                    @if (! $account->referred_by_id)
                        <form method="POST" action="{{ route('account.loyalty.referral', $account->id) }}" style="display:flex;gap:6px;">
                            @csrf
                            <input name="code" type="text" required class="form-input" placeholder="Friend's code" aria-label="Referral code for {{ $account->business?->name }}" style="width:140px" />
                            <button class="btn btn-sm btn-ghost" type="submit">Apply</button>
                        </form>
                    @endif
                </div>
                @if ($account->transactions->isNotEmpty())
                    <ul class="muted" style="margin:8px 0 0;padding-left:18px;">
                        @foreach ($account->transactions as $t)<li>{{ $t->description }} · {{ $t->points > 0 ? '+' : '' }}{{ $t->points }}</li>@endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="muted">You will see your points here after your first stay or order with a participating business.</p>
        @endforelse

        @if ($cards->isNotEmpty())
            <h2 style="margin-top:24px;">Credit & gift cards</h2>
            @foreach ($cards as $card)
                <p class="muted">{{ \App\Models\Tenant::query()->find($card->tenant_id)?->name }} · <strong>{{ $card->code }}</strong> · ₱{{ number_format((float) $card->balance, 2) }} left</p>
            @endforeach
        @endif
    </div>
</x-public-layout>
