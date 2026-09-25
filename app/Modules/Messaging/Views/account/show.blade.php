<x-public-layout :title="$thread->subject" :show-search="false">
    <div class="container section" style="max-width:820px;">
        <div class="section-head">
            <span class="eyebrow">{{ $thread->kind === 'support' ? 'Platform support' : ($thread->tenant?->name ?? 'Business') }}</span>
            <h1>{{ $thread->subject }}</h1>
        </div>
        @include('customer::partials.nav')
        <div class="card" style="padding:16px;">
            @include('messaging::partials.conversation', ['replyRoute' => route('account.messages.reply', $thread->id)])
        </div>
        @if ($thread->status === 'open')
            <form method="POST" action="{{ route('account.messages.close', $thread->id) }}" style="margin-top:10px;">@csrf<button class="btn btn-sm btn-ghost" type="submit">Close conversation</button></form>
        @endif
    </div>
</x-public-layout>
