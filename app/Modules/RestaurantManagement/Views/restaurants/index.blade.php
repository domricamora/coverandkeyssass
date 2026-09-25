<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Restaurants</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Manage profiles, menus, modifiers and tables.</p>
        </div>
        @if (auth()->user()->hasPermissionTo('restaurants.create'))
            <a href="{{ route('restaurants.create') }}" class="btn btn-primary">+ New restaurant</a>
        @endif
    </div>

    <div class="table-wrap card">
        <table class="table">
            <thead>
                <tr><th>Restaurant</th><th>Status</th><th>Menu items</th><th>Tables</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($restaurants as $restaurant)
                    <tr>
                        <td>
                            <strong style="color:var(--text)"><a href="{{ route('restaurants.show', $restaurant) }}">{{ $restaurant->name }}</a></strong>
                            <br><small style="color:var(--text-3)">{{ $restaurant->locationLabel() ?: '—' }}</small>
                        </td>
                        <td><span class="badge {{ $restaurant->isPublished() ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($restaurant->status) }}</span></td>
                        <td style="color:var(--text-2)">{{ $restaurant->menu_items_count }}</td>
                        <td style="color:var(--text-2)">{{ $restaurant->tables_count }}</td>
                        <td class="text-right">
                            <a href="{{ route('restaurants.show', $restaurant) }}" class="btn btn-sm btn-dark">Open</a>
                            <a href="{{ route('restaurants.menu', $restaurant) }}" class="btn btn-sm btn-ghost">Menu</a>
                            <a href="{{ route('restaurants.orders.index', $restaurant) }}" class="btn btn-sm btn-ghost">Orders</a>
                            <a href="{{ route('restaurants.reservations', $restaurant) }}" class="btn btn-sm btn-ghost">Reservations</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">No restaurants yet — create your first listing.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $restaurants->links() }}</div>
</x-app-layout>
