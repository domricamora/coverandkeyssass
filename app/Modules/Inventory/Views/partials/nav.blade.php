<nav class="flex gap-2 mt-2 mb-4" aria-label="Inventory">
    @foreach ([['inventory.index', 'Stock'], ['inventory.purchase-orders.index', 'Purchase orders'], ['inventory.recipes', 'Recipes']] as [$name, $label])
        <a href="{{ route($name) }}" class="btn btn-sm {{ request()->routeIs($name) ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
    @endforeach
</nav>
