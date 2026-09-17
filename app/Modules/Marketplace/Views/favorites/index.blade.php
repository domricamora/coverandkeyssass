@php
    $properties = $properties ?? collect();
    $restaurants = $restaurants ?? collect();
@endphp

<x-public-layout title="Wish list" description="Stays and restaurants you have saved on Cover & Keys." :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Saved for later</span>
            <h1>Your wish list</h1>
            <p>Private to your account — {{ $properties->count() + $restaurants->count() }} saved {{ Str::plural('item', $properties->count() + $restaurants->count()) }} on this page.</p>
        </div>

        @if ($properties->isEmpty() && $restaurants->isEmpty())
            @include('marketplace::partials.empty', [
                'heading' => 'Nothing saved yet',
                'message' => 'Browse stays and tap the heart on any listing to keep it here.',
                'actionLabel' => 'Explore stays',
                'actionUrl' => route('marketplace.hotels'),
            ])
        @else
            @if ($properties->isNotEmpty())
                <h2 class="dash-h2">Stays</h2>
                <div class="grid grid-3">
                    @foreach ($properties as $property)
                        @include('marketplace::partials.property-card', ['property' => $property, 'favoriteIds' => $properties->pluck('id')->all()])
                    @endforeach
                </div>
            @endif

            @if ($restaurants->isNotEmpty())
                <h2 class="dash-h2 mt-4">Restaurants</h2>
                <div class="grid grid-3">
                    @foreach ($restaurants as $restaurant)
                        @include('marketplace::partials.restaurant-card', ['restaurant' => $restaurant, 'favoriteIds' => $restaurants->pluck('id')->all()])
                    @endforeach
                </div>
            @endif

            {{ $favorites->links('marketplace::partials.pagination') }}
        @endif
    </div>
</x-public-layout>