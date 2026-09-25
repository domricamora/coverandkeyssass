{{-- Rating breakdown on a public listing page. $reviewSummary from ReviewsServiceProvider. --}}
@if (($reviewSummary['count'] ?? 0) > 0)
    <div class="card" style="padding:14px;margin-bottom:14px;">
        <p style="margin:0 0 8px;"><strong>{{ number_format($reviewSummary['overall'], 1) }} ★</strong> from {{ $reviewSummary['count'] }} verified {{ \Illuminate\Support\Str::plural('review', $reviewSummary['count']) }}
            @if ($reviewSummary['host']) · host rating {{ number_format($reviewSummary['host'], 1) }} ★ @endif</p>
        @if ($reviewSummary['categories'])
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:6px 16px;">
                @foreach ($reviewSummary['categories'] as $category => $avg)
                    <div class="muted" style="display:flex;align-items:center;gap:8px;">
                        <span style="width:90px;">{{ ucfirst($category) }}</span>
                        <span style="flex:1;height:6px;background:var(--border, #e5e7eb);border-radius:3px;overflow:hidden;"><span style="display:block;height:100%;width:{{ $avg * 20 }}%;background:var(--text, #111);"></span></span>
                        <span>{{ number_format($avg, 1) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($reviewSummary['rooms'])
            <p class="muted" style="margin:8px 0 0;">Rooms: @foreach ($reviewSummary['rooms'] as $room => [$avg, $n]){{ $room }} {{ number_format($avg, 1) }} ★ ({{ $n }}){{ $loop->last ? '' : ' · ' }}@endforeach</p>
        @endif
        @if ($reviewSummary['dishes'])
            <p class="muted" style="margin:8px 0 0;">Favourite dishes: @foreach (array_slice($reviewSummary['dishes'], 0, 5, true) as $dish => [$avg, $n]){{ $dish }} {{ number_format($avg, 1) }} ★{{ $loop->last ? '' : ' · ' }}@endforeach</p>
        @endif
    </div>
@endif
