<x-public-layout title="My account" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Welcome back</span>
            <h1>{{ auth()->user()->name }}</h1>
        </div>

        @include('customer::partials.nav')

        <div class="grid grid-3" style="margin-bottom:24px;">
            <a class="card" href="{{ route('marketplace.favorites.index') }}" style="padding:16px;color:inherit;"><p class="muted" style="margin:0;">Wish list</p><strong style="font-size:1.6rem;">{{ $favoritesCount }}</strong></a>
            <a class="card" href="{{ route('account.reviews') }}" style="padding:16px;color:inherit;"><p class="muted" style="margin:0;">Reviews written</p><strong style="font-size:1.6rem;">{{ $reviewsCount }}</strong></a>
            <a class="card" href="{{ route('account.notifications') }}" style="padding:16px;color:inherit;"><p class="muted" style="margin:0;">Unread notifications</p><strong style="font-size:1.6rem;">{{ $unreadCount }}</strong></a>
        </div>

        <h2 class="dash-h2">Upcoming trips</h2>
        @forelse ($upcoming as $booking)
            @include('customer::partials.trip-row')
        @empty
            @include('marketplace::partials.empty', [
                'heading' => 'No upcoming trips',
                'message' => 'Find a stay and request to book — it will show up here.',
                'actionLabel' => 'Explore stays',
                'actionUrl' => route('marketplace.hotels'),
            ])
        @endforelse

        @if ($past->isNotEmpty())
            <h2 class="dash-h2 mt-4">Past trips</h2>
            @foreach ($past as $booking)
                @include('customer::partials.trip-row')
            @endforeach
            <a href="{{ route('account.bookings.index', ['tab' => 'past']) }}">All past trips</a>
        @endif
    </div>
</x-public-layout>
