<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Notifications</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">New orders, assigned tasks and tickets, stock and subscription alerts.</p>
        </div>
        <div class="flex gap-2">
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read') }}">
                    @csrf
                    <button class="btn btn-sm btn-ghost" type="submit">Mark all as read</button>
                </form>
            @endif
            <a class="btn btn-sm btn-ghost" href="{{ route('account.notification-settings') }}">Settings</a>
        </div>
    </div>

    @forelse ($notifications as $notification)
        <div class="card" style="padding:12px 16px;margin-bottom:8px;{{ $notification->read_at ? '' : 'border-left:3px solid var(--gold);' }}">
            @if (! empty($notification->data['link']))
                <a href="{{ route('notifications.open', $notification->id) }}">{{ $notification->data['message'] ?? 'Update' }}</a>
            @else
                {{ $notification->data['message'] ?? 'Update' }}
            @endif
            <span style="display:block;color:var(--text-3);font-size:.85em;">{{ $notification->created_at->diffForHumans() }}</span>
        </div>
    @empty
        <p style="color:var(--text-3)">No notifications yet.</p>
    @endforelse

    {{ $notifications->links() }}
</x-app-layout>
