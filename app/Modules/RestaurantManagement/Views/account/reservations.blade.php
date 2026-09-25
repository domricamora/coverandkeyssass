<x-public-layout title="My table reservations" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Table reservations</h1>
        </div>

        @include('customer::partials.nav')

        @error('reservation')<p class="muted" role="alert" style="color:var(--danger, #b91c1c)">{{ $message }}</p>@enderror

        @forelse ($reservations as $r)
            <div class="card" style="padding:16px;margin-bottom:12px;display:flex;justify-content:space-between;gap:16px;align-items:center;">
                <div>
                    <strong>{{ $r->restaurant?->name ?? 'Restaurant' }}</strong>
                    <p class="muted" style="margin:4px 0 0;">
                        {{ $r->reserved_at->format('D, M j, Y · g:i A') }} · party of {{ $r->party_size }} · {{ $r->reference }}
                    </p>
                    @if ($r->special_requests)<p class="muted" style="margin:4px 0 0;">“{{ $r->special_requests }}”</p>@endif
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <span class="chip-inline">{{ $r->statusLabel() }}</span>
                    @if (in_array($r->status, ['seated', 'completed'], true) && ! \App\Modules\Marketplace\Models\Review::query()->withTrashed()->where('table_reservation_id', $r->id)->exists())
                        <details>
                            <summary class="btn btn-sm btn-ghost">Review</summary>
                            <form method="POST" action="{{ route('account.reservations.review', $r->reference) }}" style="display:grid;gap:6px;margin-top:6px;min-width:240px;">
                                @csrf
                                <select class="form-input" name="rating" aria-label="Rating">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} ★</option>@endfor</select>
                                <textarea class="form-input" name="comment" rows="2" required placeholder="How was your visit?" aria-label="Review"></textarea>
                                <button class="btn btn-sm btn-primary" type="submit">Publish</button>
                            </form>
                        </details>
                    @endif
                    @if ($r->guestCancellable())
                        <form method="POST" action="{{ route('account.reservations.cancel', $r->reference) }}" onsubmit="return confirm('Cancel this reservation?')">
                            @csrf
                            <button class="btn btn-sm btn-ghost" type="submit">Cancel</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted">No table reservations yet.</p>
        @endforelse

        {{ $reservations->links('marketplace::partials.pagination') }}
    </div>
</x-public-layout>
