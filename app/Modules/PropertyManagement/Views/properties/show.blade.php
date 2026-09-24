<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $property->name }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $property->propertyType?->name ?? 'Property' }} · {{ $property->locationLabel() ?: 'No destination set' }}
                @if ($property->is_featured) · <span class="badge badge-amber">Featured</span>@endif
            </p>
        </div>
        <div class="flex gap-2">
            @if ($property->status === \App\Modules\Marketplace\Models\Property::STATUS_PUBLISHED)
                <form method="POST" action="{{ route('properties.unpublish', $property) }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Unpublish</button>
                </form>
            @else
                <form method="POST" action="{{ route('properties.publish', $property) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Publish</button>
                </form>
            @endif
            <form method="POST" action="{{ route('properties.destroy', $property) }}" onsubmit="return confirm('Delete this property? Its inventory stays for records but the listing disappears.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-4 mt-4">
        <div class="card p-4">
            <p class="text-sm" style="color:var(--text-3)">Status</p>
            <span class="badge {{ $property->status === \App\Modules\Marketplace\Models\Property::STATUS_PUBLISHED ? 'badge-green' : 'badge-gray' }} mt-1">{{ ucfirst($property->status) }}</span>
        </div>
        <div class="card p-4">
            <p class="text-sm" style="color:var(--text-3)">Room types</p>
            <h2>{{ $property->room_types_count }}</h2>
        </div>
        <div class="card p-4">
            <p class="text-sm" style="color:var(--text-3)">Rooms</p>
            <h2>{{ $property->rooms_count }}</h2>
        </div>
        <div class="card p-4">
            <p class="text-sm" style="color:var(--text-3)">Property staff</p>
            <h2>{{ $property->staff_count }}</h2>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mt-6">
        <div class="card p-6">
            <h2 class="text-lg">Profile</h2>
            <p class="mt-2 text-sm" style="color:var(--text-2)">{{ $property->tagline ?? 'No tagline yet.' }}</p>
            <p class="mt-2 text-sm" style="color:var(--text-2)">{{ $property->description ?? 'No description yet.' }}</p>
            <dl class="mt-4 text-sm space-y-1" style="color:var(--text-2)">
                <dt><strong>From</strong> {{ $property->priceLabel() }} / night · cleaning {{ $property->currency }} {{ number_format((float) $property->cleaning_fee, 0) }}</dt>
                <dt><strong>Capacity</strong> up to {{ $property->max_guests }} guests · {{ $property->bedrooms }} bedroom(s) · {{ $property->beds }} bed(s) · {{ $property->bathrooms }} bath(s)</dt>
                <dt><strong>Check-in</strong> {{ $property->check_in_time }} · <strong>Check-out</strong> {{ $property->check_out_time }}</dt>
                @if ($property->amenities->isNotEmpty())
                    <dt><strong>Amenities</strong> {{ $property->amenities->pluck('name')->implode(', ') }}</dt>
                @endif
            </dl>
            @if (($property->policies ?? []) !== [])
                <h3 class="mt-4 text-sm font-semibold">Policies</h3>
                <ul class="mt-1 text-sm space-y-1" style="color:var(--text-2)">
                    @foreach ($property->policies as $key => $value)
                        <li><strong>{{ ucfirst($key) }}:</strong> {{ $value }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="flex gap-2 mt-5">
                <a href="{{ route('properties.edit', $property) }}" class="btn btn-sm btn-dark">Edit profile</a>
                <a href="{{ route('properties.inventory', $property) }}" class="btn btn-sm btn-ghost">Inventory & rates</a>
                <a href="{{ route('properties.staff.index', $property) }}" class="btn btn-sm btn-ghost">Staff</a>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="text-lg">Media</h2>
            @php($cover = $property->coverMedia())
            @if ($cover)
                <img src="{{ $cover->url() }}" alt="{{ $cover->alt ?? $property->name }}" class="mt-3 rounded" style="width:100%;height:220px;object-fit:cover" />
            @else
                <p class="mt-2 text-sm" style="color:var(--text-3)">No cover yet — add photos in the profile editor.</p>
            @endif
            <p class="mt-3 text-sm" style="color:var(--text-3)">
                {{ $property->media->where('kind', 'image')->count() }} photo(s) · {{ $property->media->where('kind', 'video')->count() }} video(s)
            </p>
            @if ($property->status !== \App\Modules\Marketplace\Models\Property::STATUS_PUBLISHED)
                <p class="mt-2 text-sm" style="color:var(--text-3)">Drafts are invisible on the marketplace until published.</p>
            @endif
        </div>
    </div>
</x-app-layout>
