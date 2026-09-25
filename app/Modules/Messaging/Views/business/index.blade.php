<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Messages</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Guests writing about your stays, restaurants, orders and tables — and staff conversations.</p>
        </div>
        <div class="flex gap-2">
            @foreach (['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $key => $label)
                <a href="{{ route('messages.index', ['status' => $key]) }}" class="btn btn-sm {{ request('status', 'open') === $key ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <x-input-error :messages="$errors->get('participants')" />

    <div class="grid grid-cols-3 gap-4">
        <div class="card p-4" style="grid-column:span 2">
            @forelse ($threads as $thread)
                @php($unread = $thread->unreadFor(auth()->user()))
                <a href="{{ route('messages.show', $thread->id) }}" class="flex justify-between items-center p-3" style="border-bottom:1px solid var(--border);color:inherit;">
                    <span>
                        <strong style="color:var(--text)">{{ $thread->subject }}</strong>
                        <br><small style="color:var(--text-3)">{{ $thread->kind === 'staff' ? 'Staff · '.$thread->participants->count().' people' : ($thread->guest?->name ?? 'Guest') }} · {{ $thread->last_message_at?->diffForHumans() }}</small>
                    </span>
                    @if ($unread)<span class="badge badge-amber">{{ $unread }} new</span>@endif
                </a>
            @empty
                <p class="text-sm p-3" style="color:var(--text-3)">No conversations.</p>
            @endforelse
            <div class="mt-3">{{ $threads->links() }}</div>
        </div>

        @if ($members->isNotEmpty())
            <form method="POST" action="{{ route('messages.staff.store') }}" class="card p-6 space-y-2">
                @csrf
                <h2 class="text-lg">Message colleagues</h2>
                <select name="participants[]" multiple required class="form-input" size="5" aria-label="Colleagues">
                    @foreach ($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach
                </select>
                <input name="subject" type="text" required class="form-input" placeholder="Subject" aria-label="Subject" />
                <textarea name="body" rows="3" required class="form-input" placeholder="Message" aria-label="Message"></textarea>
                <button type="submit" class="btn btn-primary w-full">Send</button>
            </form>
        @endif
    </div>
</x-app-layout>
