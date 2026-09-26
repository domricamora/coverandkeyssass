{{-- Rating breakdown on a public listing page (Booking.com-style score + categories). $reviewSummary from ReviewsServiceProvider. --}}
@if (($reviewSummary['count'] ?? 0) > 0)
    @php
        $overall = (float) $reviewSummary['overall'];
        $word = match (true) {
            $overall >= 4.8 => 'Exceptional',
            $overall >= 4.5 => 'Excellent',
            $overall >= 4.0 => 'Very good',
            $overall >= 3.5 => 'Good',
            default => 'Pleasant',
        };
    @endphp
    <div class="border border-line bg-white p-5" style="margin-bottom:14px;">
        <div class="flex flex-wrap items-center gap-4">
            <span class="flex h-14 w-14 items-center justify-center bg-brand font-display text-[22px] font-medium text-white">{{ number_format($overall, 1) }}</span>
            <div>
                <p class="text-[18px] font-semibold text-fg" style="margin:0;">{{ $word }}</p>
                <p class="text-[14px] text-fg-3" style="margin:0;">
                    {{ $reviewSummary['count'] }} verified {{ \Illuminate\Support\Str::plural('review', $reviewSummary['count']) }} from guests who stayed or dined
                    @if ($reviewSummary['host']) · host rating {{ number_format($reviewSummary['host'], 1) }} @endif
                </p>
            </div>
        </div>

        @if ($reviewSummary['categories'])
            <div class="mt-5 grid gap-x-8 gap-y-3 sm:grid-cols-2">
                @foreach ($reviewSummary['categories'] as $category => $avg)
                    <div>
                        <div class="flex justify-between text-[14px]"><span class="text-fg-2">{{ ucfirst($category) }}</span><strong class="font-semibold tabular-nums text-fg">{{ number_format($avg, 1) }}</strong></div>
                        <span class="mt-1.5 block h-1.5 bg-soft"><span class="block h-full {{ $avg >= 4.5 ? 'bg-brand' : ($avg >= 3.5 ? 'bg-[#3f8f8b]' : 'bg-coral') }}" style="width:{{ $avg * 20 }}%"></span></span>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($reviewSummary['rooms'])
            <p class="mt-4 text-[14px] text-fg-3" style="margin-bottom:0;">Rooms: @foreach ($reviewSummary['rooms'] as $room => [$avg, $n]){{ $room }} {{ number_format($avg, 1) }} ({{ $n }}){{ $loop->last ? '' : ' · ' }}@endforeach</p>
        @endif
        @if ($reviewSummary['dishes'])
            <p class="mt-2 text-[14px] text-fg-3" style="margin-bottom:0;">Favourite dishes: @foreach (array_slice($reviewSummary['dishes'], 0, 5, true) as $dish => [$avg, $n]){{ $dish }} {{ number_format($avg, 1) }}{{ $loop->last ? '' : ' · ' }}@endforeach</p>
        @endif
    </div>
@endif
