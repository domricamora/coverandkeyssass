<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Properties</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Manage listings, room inventory, rates and availability.</p>
        </div>
        <a href="{{ route('properties.create') }}" class="btn btn-primary">+ New property</a>
    </div>

    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr>
                    <th>Property</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>From</th>
                    <th>Rooms</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($properties as $property)
                    <tr>
                        <td>
                            <strong style="color:var(--text)">
                                <a href="{{ route('properties.show', $property) }}">{{ $property->name }}</a>
                            </strong>
                            <br><small style="color:var(--text-3)">{{ $property->locationLabel() ?: '—' }}</small>
                        </td>
                        <td style="color:var(--text-2)">{{ $property->propertyType?->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ match ($property->status) {
                                \App\Modules\Marketplace\Models\Property::STATUS_PUBLISHED => 'badge-green',
                                \App\Modules\Marketplace\Models\Property::STATUS_DRAFT => 'badge-gray',
                                \App\Modules\Marketplace\Models\Property::STATUS_PENDING => 'badge-amber',
                                default => 'badge-amber',
                            } }}">
                                {{ ucfirst($property->status) }}
                            </span>
                        </td>
                        <td style="color:var(--text-2)">{{ $property->priceLabel() }}</td>
                        <td style="color:var(--text-2)">{{ $property->rooms_count }}</td>
                        <td class="text-right">
                            <a href="{{ route('properties.show', $property) }}" class="btn btn-sm btn-dark">Open</a>
                            <a href="{{ route('properties.inventory', $property) }}" class="btn btn-sm btn-ghost">Inventory</a>
                            <a href="{{ route('properties.staff.index', $property) }}" class="btn btn-sm btn-ghost">Staff</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm" style="color:var(--text-3)">No properties yet — create your first listing.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $properties->links() }}</div>
</x-app-layout>
