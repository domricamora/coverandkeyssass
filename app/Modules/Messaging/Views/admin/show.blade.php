<x-app-layout>
    <div class="dash-row-head">
        <div><h1>{{ $thread->subject }}</h1><p class="mt-1 text-sm" style="color:var(--text-3)">{{ $thread->guest?->name }} · {{ $thread->guest?->email }} · {{ ucfirst($thread->status) }}</p></div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.support.status', $thread->id) }}">@csrf<input type="hidden" name="open" value="{{ $thread->status === 'open' ? 0 : 1 }}" /><button class="btn btn-ghost" type="submit">{{ $thread->status === 'open' ? 'Close' : 'Reopen' }}</button></form>
            <a href="{{ route('admin.support.index') }}" class="btn btn-ghost">Support</a>
        </div>
    </div>
    <div class="card p-6 mt-4" style="max-width:900px">
        @include('messaging::partials.conversation', ['replyRoute' => route('admin.support.reply', $thread->id)])
    </div>
</x-app-layout>
