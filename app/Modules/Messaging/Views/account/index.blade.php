<x-public-layout title="Messages" :show-search="false">
    <div class="container section">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Messages</h1>
        </div>
        @include('customer::partials.nav')
        <p><a class="btn btn-sm btn-ghost" href="{{ route('account.messages.create') }}">Contact Cover &amp; Keys support</a></p>

        @forelse ($threads as $thread)
            @php($unread = $thread->unreadFor(auth()->user()))
            <a href="{{ route('account.messages.show', $thread->id) }}" class="card" style="padding:14px;margin-bottom:10px;display:flex;justify-content:space-between;gap:12px;color:inherit;text-decoration:none;">
                <div>
                    <strong>{{ $thread->subject }}</strong>
                    <p class="muted" style="margin:4px 0 0;">{{ $thread->kind === 'support' ? 'Platform support' : ($thread->tenant?->name ?? 'Business') }} · {{ $thread->last_message_at?->diffForHumans() }}{{ $thread->status === 'closed' ? ' · closed' : '' }}</p>
                </div>
                @if ($unread)<span class="chip-inline">{{ $unread }} new</span>@endif
            </a>
        @empty
            <p class="muted">No conversations yet. Use "Message the host" on a stay or restaurant page, or on your trips and orders.</p>
        @endforelse
        {{ $threads->links('marketplace::partials.pagination') }}
    </div>
</x-public-layout>
