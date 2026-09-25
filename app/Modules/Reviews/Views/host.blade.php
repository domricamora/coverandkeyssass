@php($canReply = auth()->user()->hasPermissionTo('reviews.reply'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Reviews</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Average {{ number_format($average, 1) }} ★ · {{ $unanswered }} without a reply</p>
        </div>
        <div class="flex gap-2">
            @foreach (['' => 'All', 'unanswered' => 'Unanswered', 'low' => '1–2 stars'] as $key => $label)
                <a href="{{ route('reviews.index', $key ? ['filter' => $key] : []) }}" class="btn btn-sm {{ request('filter', '') === $key ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <x-input-error :messages="$errors->get('host_response')" />

    @forelse ($reviews as $review)
        <div class="card p-6 mt-4 text-sm" style="color:var(--text-2)">
            <div class="flex justify-between">
                <div>
                    <strong style="color:var(--text)">{{ $review->user?->name ?? 'Guest' }}</strong> · {{ $review->rating }} ★ · {{ $review->reviewable?->name }}
                    {{ $review->roomType ? '· '.$review->roomType->name : '' }}
                    · {{ $review->booking_id ? 'stay' : ($review->order_id ? 'order' : ($review->table_reservation_id ? 'table visit' : 'review')) }}
                    · {{ $review->created_at->format('M j, Y') }}
                    @if ($review->status !== 'published')<span class="badge badge-gray">{{ $review->status }}</span>@endif
                    @if ($review->flagged_at)<span class="badge badge-amber">reported</span>@endif
                </div>
                <div class="flex gap-2">
                    @foreach (\App\Modules\Marketplace\Models\Review::CATEGORIES as $c)
                        @if ($review->{'rating_'.$c})<small>{{ ucfirst($c) }} {{ $review->{'rating_'.$c} }}</small>@endif
                    @endforeach
                </div>
            </div>
            @if ($review->title)<p class="mt-2"><strong>{{ $review->title }}</strong></p>@endif
            <p class="mt-1">{{ $review->comment }}</p>
            @if ($review->itemRatings->isNotEmpty())
                <p class="mt-1" style="color:var(--text-3)">Dishes: @foreach ($review->itemRatings as $ir){{ $ir->menuItem?->name }} {{ $ir->rating }}★{{ $loop->last ? '' : ' · ' }}@endforeach</p>
            @endif
            @if ($review->host_response)
                <p class="mt-2 p-3" style="background:var(--surface-2, #f7f7f7);border-radius:8px;"><strong>Your reply:</strong> {{ $review->host_response }}</p>
            @endif
            @if ($canReply)
                <div class="flex gap-4 mt-3">
                    <form method="POST" action="{{ route('reviews.reply', $review->id) }}" class="flex gap-2 flex-1">
                        @csrf
                        <input name="host_response" type="text" required class="form-input" placeholder="{{ $review->host_response ? 'Edit your reply' : 'Reply publicly' }}" aria-label="Reply" />
                        <button type="submit" class="btn btn-sm btn-primary">Reply</button>
                    </form>
                    @unless ($review->flagged_at)
                        <form method="POST" action="{{ route('reviews.flag', $review->id) }}" class="flex gap-2">
                            @csrf
                            <input name="flag_reason" type="text" required class="form-input" placeholder="Why report?" style="width:160px" aria-label="Report reason" />
                            <button type="submit" class="btn btn-sm btn-ghost">Report</button>
                        </form>
                    @endunless
                </div>
            @endif
        </div>
    @empty
        <div class="card p-6 mt-4 text-sm" style="color:var(--text-3)">No reviews yet.</div>
    @endforelse
    <div class="mt-4">{{ $reviews->links() }}</div>
</x-app-layout>
