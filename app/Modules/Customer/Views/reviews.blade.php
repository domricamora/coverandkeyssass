<x-public-layout title="My reviews" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Reviews</h1>
        </div>

        @include('customer::partials.nav')

        @forelse ($reviews as $review)
            <div class="card" style="padding:14px 16px;margin-bottom:10px;">
                <strong>{{ $review->reviewable?->name ?? 'Listing' }}</strong>
                <span class="muted">· {{ $review->rating }}/5 · {{ Str::headline($review->status) }} · {{ $review->created_at->format('M j, Y') }}</span>
                @if ($review->title)<p style="margin:6px 0 0;"><strong>{{ $review->title }}</strong></p>@endif
                <p style="margin:6px 0 0;">{{ $review->comment }}</p>
            </div>
        @empty
            <p class="muted">You have not written any reviews yet. You can review a stay after you check out.</p>
        @endforelse

        {{ $reviews->links('marketplace::partials.pagination') }}
    </div>
</x-public-layout>
