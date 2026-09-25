<nav class="flex flex-wrap gap-2" style="margin:12px 0 24px;" aria-label="Account">
    @foreach ([
        ['account.dashboard', 'Overview'],
        ['account.bookings.index', 'Trips'],
        ['account.reservations.index', 'Tables'],
        ['account.payments.index', 'Payments'],
        ['marketplace.favorites.index', 'Wish list'],
        ['account.reviews', 'Reviews'],
        ['account.notifications', 'Notifications'],
        ['profile.edit', 'Profile'],
    ] as [$name, $label])
        <a class="btn btn-sm {{ request()->routeIs($name) ? 'btn-dark' : 'btn-ghost' }}" href="{{ route($name) }}">{{ $label }}</a>
    @endforeach
</nav>
