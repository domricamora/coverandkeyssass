{{-- Verified badges + "report this listing" (Phase 29). Needs $listing and $kind (properties|restaurants). --}}
<div class="flex flex-wrap items-center gap-2" style="margin:6px 0;">
    @if ($listing->isSponsored())<span class="badge">Sponsored</span>@endif
    @if ($listing->isVerified())<span class="badge badge-green" title="Checked by the {{ config('app.name') }} team">✓ Verified {{ $kind === 'properties' ? 'property' : 'restaurant' }}</span>@endif
    @if ($listing->tenant?->verified_at)<span class="badge badge-green">✓ Verified host</span>@endif
    @auth
        <details style="margin-left:auto;">
            <summary class="muted" style="cursor:pointer;font-size:.9em;">Report this listing</summary>
            <form method="POST" action="{{ route('listings.report', [$kind, $listing->slug]) }}" class="card" style="padding:12px;margin-top:6px;display:grid;gap:8px;min-width:260px;">
                @csrf
                <select name="reason" required class="form-input" aria-label="Reason">
                    @foreach (\App\Modules\PlatformAdmin\Models\ContentReport::REASONS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <textarea name="details" rows="3" maxlength="1000" class="form-input" placeholder="What's wrong? (optional)" aria-label="Details"></textarea>
                <button type="submit" class="btn btn-sm btn-dark">Send report</button>
            </form>
        </details>
    @endauth
</div>
