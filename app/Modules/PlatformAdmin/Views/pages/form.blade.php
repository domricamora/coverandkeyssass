<x-app-layout>
    @include('platform-admin::partials.head', ['title' => $page->exists ? 'Edit page' : 'New page'])

    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="card mt-6 p-6" style="display:grid;gap:12px;max-width:900px">
        @csrf
        @if ($page->exists) @method('PUT') @endif
        <label style="display:grid;gap:4px"><strong>Title</strong><input name="title" required class="form-input" value="{{ old('title', $page->title) }}" /></label>
        <label style="display:grid;gap:4px"><strong>Address</strong><span class="flex items-center gap-1">/pages/<input name="slug" required pattern="[A-Za-z0-9_-]+" class="form-input" value="{{ old('slug', $page->slug) }}" /></span></label>
        <label style="display:grid;gap:4px"><strong>Meta description</strong><input name="meta_description" maxlength="300" class="form-input" value="{{ old('meta_description', $page->meta_description) }}" /></label>
        <label style="display:grid;gap:4px"><strong>Body</strong> <small style="color:var(--text-3)">Markdown: # heading, **bold**, [link](https://…), - lists. HTML is not allowed.</small>
            <textarea name="body" rows="18" required class="form-input" style="font-family:monospace">{{ old('body', $page->body) }}</textarea>
        </label>
        <label><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published)) /> Published</label>
        <label><input type="checkbox" name="in_footer" value="1" @checked(old('in_footer', $page->in_footer)) /> Link in the site footer</label>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('admin.pages.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</x-app-layout>
