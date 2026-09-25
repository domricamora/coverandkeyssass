<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $thread->subject }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $thread->kind === 'staff' ? 'Staff: '.$thread->participants->pluck('user.name')->implode(', ') : 'Guest: '.($thread->guest?->name ?? '—').' · '.$thread->guest?->email }}
                {{ $thread->about_type ? '· about '.class_basename($thread->about_type).' #'.$thread->about_id : '' }} · {{ ucfirst($thread->status) }}
            </p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('messages.status', $thread->id) }}">@csrf<input type="hidden" name="open" value="{{ $thread->status === 'open' ? 0 : 1 }}" /><button class="btn btn-ghost" type="submit">{{ $thread->status === 'open' ? 'Close' : 'Reopen' }}</button></form>
            <a href="{{ route('messages.index') }}" class="btn btn-ghost">Inbox</a>
        </div>
    </div>
    <div class="card p-6 mt-4" style="max-width:900px">
        @include('messaging::partials.conversation', ['replyRoute' => route('messages.reply', $thread->id)])
    </div>
</x-app-layout>
