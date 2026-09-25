<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $restaurant->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $restaurant->cuisines->pluck('name')->implode(', ') ?: 'Restaurant' }} · {{ $restaurant->locationLabel() ?: 'No destination set' }} · {{ $restaurant->priceLevelLabel() }}
            </p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route($restaurant->isPublished() ? 'restaurants.unpublish' : 'restaurants.publish', $restaurant) }}">
                @csrf
                <button type="submit" class="btn {{ $restaurant->isPublished() ? 'btn-ghost' : 'btn-primary' }}">{{ $restaurant->isPublished() ? 'Unpublish' : 'Publish' }}</button>
            </form>
            <form method="POST" action="{{ route('restaurants.destroy', $restaurant) }}" onsubmit="return confirm('Delete this restaurant?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-4 mt-4">
        <div class="card p-4">
            <p class="text-sm" style="color:var(--text-3)">Status</p>
            <span class="badge {{ $restaurant->isPublished() ? 'badge-green' : 'badge-gray' }} mt-1">{{ ucfirst($restaurant->status) }}</span>
        </div>
        <div class="card p-4"><p class="text-sm" style="color:var(--text-3)">Menu categories</p><h2>{{ $restaurant->menu_categories_count }}</h2></div>
        <div class="card p-4"><p class="text-sm" style="color:var(--text-3)">Menu items</p><h2>{{ $restaurant->menu_items_count }}</h2></div>
        <div class="card p-4"><p class="text-sm" style="color:var(--text-3)">Tables</p><h2>{{ $restaurant->tables_count }} <small style="color:var(--text-3)">in {{ $restaurant->dining_areas_count }} area(s)</small></h2></div>
    </div>

    <div class="grid grid-cols-2 gap-4 mt-6">
        <div class="card p-6">
            <h2 class="text-lg">Profile</h2>
            <p class="mt-2 text-sm" style="color:var(--text-2)">{{ $restaurant->tagline ?? 'No tagline yet.' }}</p>
            <p class="mt-2 text-sm" style="color:var(--text-2)">{{ $restaurant->description ?? 'No description yet.' }}</p>
            <dl class="mt-4 text-sm space-y-1" style="color:var(--text-2)">
                <dt><strong>Address</strong> {{ $restaurant->address_line ?: '—' }}</dt>
                <dt><strong>Contact</strong> {{ $restaurant->phone ?: '—' }} · {{ $restaurant->email ?: '—' }}</dt>
                <dt><strong>Reservations</strong> {{ $restaurant->reservations_enabled ? 'On' : 'Off' }} · <strong>Delivery</strong> {{ $restaurant->delivery_enabled ? 'On' : 'Off' }}</dt>
            </dl>
            @if ($restaurant->opening_hours)
                <h3 class="mt-4 text-sm font-semibold">Opening hours</h3>
                <ul class="mt-1 text-sm space-y-1" style="color:var(--text-2)">
                    @foreach ($restaurant->opening_hours as $day => $time)
                        <li><strong>{{ ucfirst($day) }}:</strong> {{ $time }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="flex gap-2 mt-5">
                <a href="{{ route('restaurants.edit', $restaurant) }}" class="btn btn-sm btn-dark">Edit profile</a>
                <a href="{{ route('restaurants.menu', $restaurant) }}" class="btn btn-sm btn-ghost">Menu</a>
                <a href="{{ route('restaurants.tables', $restaurant) }}" class="btn btn-sm btn-ghost">Tables</a>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="text-lg">Photos</h2>
            @php($cover = $restaurant->coverMedia())
            @if ($cover)
                <img src="{{ $cover->url() }}" alt="{{ $cover->alt ?? $restaurant->name }}" class="mt-3 rounded" style="width:100%;height:220px;object-fit:cover" />
            @else
                <p class="mt-2 text-sm" style="color:var(--text-3)">No cover yet — add photos in the profile editor.</p>
            @endif
            @unless ($restaurant->isPublished())
                <p class="mt-2 text-sm" style="color:var(--text-3)">Drafts are invisible on the marketplace until published.</p>
            @endunless
        </div>
    </div>
</x-app-layout>
