<x-app-layout>
    <div class="dash-row-head">
        <div><h1>Support</h1><p class="mt-1 text-sm" style="color:var(--text-3)">Guests and hosts writing to the platform.</p></div>
        <div class="flex gap-2">
            @foreach (['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.support.index', ['status' => $key]) }}" class="btn btn-sm {{ request('status', 'open') === $key ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div class="card p-4">
        @forelse ($threads as $thread)
            @php($unread = $thread->unreadFor(auth()->user()))
            <a href="{{ route('admin.support.show', $thread->id) }}" class="flex justify-between items-center p-3" style="border-bottom:1px solid var(--border);color:inherit;">
                <span><strong style="color:var(--text)">{{ $thread->subject }}</strong><br><small style="color:var(--text-3)">{{ $thread->guest?->name }} · {{ $thread->guest?->email }} · {{ $thread->last_message_at?->diffForHumans() }}</small></span>
                @if ($unread)<span class="badge badge-amber">{{ $unread }} new</span>@endif
            </a>
        @empty
            <p class="text-sm p-3" style="color:var(--text-3)">No support conversations.</p>
        @endforelse
        <div class="mt-3">{{ $threads->links() }}</div>
    </div>
</x-app-layout>
