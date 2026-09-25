{{--
    Listing photo upload (properties + restaurants): several files at once,
    tagged with a tour area; converted to WebP on the server (MediaUploads).
    Params: $action (form URL), $areas (suggested area names), $listId.
--}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mt-5 grid gap-3" style="border:1px dashed var(--input-border);padding:16px">
    @csrf
    <input type="hidden" name="kind" value="image" />
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label for="{{ $listId }}-photos" class="form-label">Upload photos</label>
            <input id="{{ $listId }}-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="form-input" style="padding:7px" />
            <p class="mt-1 text-xs" style="color:var(--text-3)">JPG, PNG or WebP, up to 8 MB each, 20 at a time. Saved as WebP.</p>
            <x-input-error :messages="array_merge($errors->get('photos'), \Illuminate\Support\Arr::flatten($errors->get('photos.*')))" />
        </div>
        <div>
            <label for="{{ $listId }}-caption" class="form-label">Tour area</label>
            <input id="{{ $listId }}-caption" name="caption" list="{{ $listId }}-areas" maxlength="60" class="form-input" placeholder="e.g. {{ $areas[1] ?? $areas[0] }}" />
            <datalist id="{{ $listId }}-areas">
                @foreach ($areas as $area)<option value="{{ $area }}">@endforeach
            </datalist>
            <p class="mt-1 text-xs" style="color:var(--text-3)">Guests browse the photo tour by area.</p>
        </div>
    </div>
    <details>
        <summary class="text-sm" style="color:var(--text-2);cursor:pointer">Or add a photo by link</summary>
        <input name="url" type="url" class="form-input mt-2" placeholder="https://…" />
        <x-input-error :messages="$errors->get('url')" />
    </details>
    <div><button type="submit" class="btn btn-primary">Add to tour</button></div>
</form>
