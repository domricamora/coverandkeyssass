<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'CMS pages', 'sub' => 'About, terms, privacy, help — served at /pages/{slug}.'])

    <p class="mt-4"><a href="{{ route('admin.pages.create') }}" class="btn btn-dark btn-sm">New page</a></p>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th scope="col">Title</th><th scope="col">Address</th><th scope="col">Status</th><th scope="col"></th></tr></thead>
            <tbody>
                @forelse ($pages as $page)
                    <tr>
                        <td><a href="{{ route('admin.pages.edit', $page) }}"><strong>{{ $page->title }}</strong></a></td>
                        <td><a href="{{ route('pages.show', $page->slug) }}">/pages/{{ $page->slug }}</a></td>
                        <td>{{ $page->is_published ? 'Published' : 'Draft' }}{{ $page->in_footer ? ' · footer' : '' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-ghost" type="submit" onclick="return confirm('Delete {{ $page->title }}?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="color:var(--text-3)">No pages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
