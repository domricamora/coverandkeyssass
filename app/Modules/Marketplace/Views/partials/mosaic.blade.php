{{--
    Listing hero mosaic: one large photo + up to four, each opening the
    photo tour (partials/tour listens for `tour-open`). Params: $images, $name.
--}}
<div class="mosaic {{ count($images) >= 5 ? 'mosaic--full' : (count($images) > 1 ? 'mosaic--pair' : '') }}">
    @foreach (array_slice($images, 0, count($images) >= 5 ? 5 : min(2, count($images))) as $k => $image)
        <button type="button" class="mosaic__cell {{ $k === 0 ? 'mosaic__cell--lead' : '' }}" @click="$dispatch('tour-open', {{ $k }})" aria-label="Open photo {{ $k + 1 }} of the tour">
            <img src="{{ $image }}" alt="{{ $name }}{{ $k ? ' photo '.($k + 1) : '' }}" @if ($k) loading="lazy" @endif decoding="async">
        </button>
    @endforeach
    @if (count($images) > 1)
        <button type="button" class="mosaic__all" @click="$dispatch('tour-open', 0)">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
            Show all {{ count($images) }} photos
        </button>
    @endif
</div>
