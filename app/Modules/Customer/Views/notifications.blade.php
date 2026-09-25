<x-public-layout title="Notifications" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Notifications</h1>
        </div>

        @include('customer::partials.nav')

        <p><a class="btn btn-sm btn-ghost" href="{{ route('account.notification-settings') }}">Notification settings</a></p>

        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('account.notifications.read') }}" style="margin-bottom:12px;">
                @csrf
                <button class="btn btn-sm btn-ghost" type="submit">Mark all as read</button>
            </form>
        @endif

        @forelse ($notifications as $notification)
            <div class="card" style="padding:12px 16px;margin-bottom:8px;{{ $notification->read_at ? '' : 'border-left:3px solid var(--gold);' }}">
                @if (! empty($notification->data['link']))
                    <a href="{{ route('notifications.open', $notification->id) }}">{{ $notification->data['message'] ?? 'Update' }}</a>
                @elseif (! empty($notification->data['booking_reference']))
                    <a href="{{ route('account.bookings.show', $notification->data['booking_reference']) }}">{{ $notification->data['message'] ?? 'Booking update' }}</a>
                @else
                    {{ $notification->data['message'] ?? 'Update' }}
                @endif
                <span class="muted" style="display:block;">{{ $notification->created_at->diffForHumans() }}</span>
            </div>
        @empty
            <p class="muted">No notifications yet.</p>
        @endforelse

        {{ $notifications->links('marketplace::partials.pagination') }}
    </div>
</x-public-layout>
