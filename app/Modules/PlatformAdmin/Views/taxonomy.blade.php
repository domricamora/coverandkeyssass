<x-app-layout>
    @include('platform-admin::partials.head', ['title' => 'Categories & locations', 'sub' => 'What guests filter by. Hide an entry instead of deleting it once listings use it.'])

    <div class="flex flex-wrap gap-2 mt-4">
        @foreach ($types as $key => $name)
            <a href="{{ route('admin.taxonomy.index', $key) }}" class="btn btn-sm {{ $type === $key ? 'btn-dark' : 'btn-ghost' }}">{{ $name }}</a>
        @endforeach
    </div>

    @php($flagLabel = $type === 'locations' ? 'Featured destination' : 'Visible')
    <div class="card mt-4 p-6">
        <h2 class="dash-h2" style="margin-top:0">Add to {{ strtolower($label) }}</h2>
        <form method="POST" action="{{ route('admin.taxonomy.store', $type) }}" class="flex flex-wrap gap-2 mt-2">
            @csrf
            <input name="name" required class="form-input" placeholder="Name" aria-label="Name" style="max-width:220px" />
            <input name="slug" class="form-input" placeholder="slug (auto)" aria-label="Slug" style="max-width:180px" />
            @if ($type === 'locations')<input name="region" class="form-input" placeholder="Region" aria-label="Region" style="max-width:160px" />@endif
            @if (in_array($type, ['property-types', 'amenities']))<input name="category" class="form-input" placeholder="Group (e.g. hotel, general)" aria-label="Group" style="max-width:180px" />@endif
            <input name="sort_order" type="number" min="0" class="form-input" placeholder="Order" aria-label="Sort order" style="max-width:90px" />
            <label class="text-sm"><input type="checkbox" name="flag" value="1" @checked($type !== 'locations') /> {{ $flagLabel }}</label>
            <button class="btn btn-sm btn-primary" type="submit">Add</button>
        </form>
    </div>

    <div class="table-wrap card mt-4">
        <table class="table">
            <thead><tr><th scope="col">Name</th><th scope="col">Slug</th><th scope="col">Order</th><th scope="col">{{ $flagLabel }}</th><th scope="col"></th></tr></thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td colspan="4">
                            <form method="POST" action="{{ route('admin.taxonomy.update', [$type, $item->id]) }}" class="flex flex-wrap gap-2 items-center">
                                @csrf @method('PUT')
                                <input name="name" required value="{{ $item->name }}" class="form-input" aria-label="Name" style="max-width:200px" />
                                <input name="slug" required value="{{ $item->slug }}" class="form-input" aria-label="Slug" style="max-width:170px" />
                                @if ($type === 'locations')<input name="region" value="{{ $item->region }}" class="form-input" aria-label="Region" style="max-width:140px" />@endif
                                @if (in_array($type, ['property-types', 'amenities']))<input name="category" value="{{ $item->category }}" class="form-input" aria-label="Group" style="max-width:140px" />@endif
                                <input name="sort_order" type="number" min="0" value="{{ $item->sort_order }}" class="form-input" aria-label="Sort order" style="max-width:80px" />
                                <label><input type="checkbox" name="flag" value="1" @checked($type === 'locations' ? $item->is_featured : $item->is_active) aria-label="{{ $flagLabel }}" /></label>
                                <button class="btn btn-sm btn-ghost" type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.taxonomy.destroy', [$type, $item->id]) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-ghost" type="submit" onclick="return confirm('Delete {{ $item->name }}?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
