<x-public-layout :title="$page->title" :description="$page->meta_description" :show-search="false">
    <article class="container section" style="max-width:780px;">
        @unless ($page->is_published)<p class="card" style="padding:8px 12px;">Draft preview — only Super Admins can see this.</p>@endunless
        <h1>{{ $page->title }}</h1>
        <div class="prose" style="line-height:1.7">{!! $page->html() !!}</div>
        <p class="muted" style="margin-top:32px;">Last updated {{ $page->updated_at->format('F j, Y') }}</p>
    </article>
</x-public-layout>
