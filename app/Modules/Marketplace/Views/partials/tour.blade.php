{{--
    Guest photo tour for a property or restaurant: area chips, a photo grid
    and a fullscreen viewer (arrows / swipe / Esc). Photos are the listing's
    images grouped by media.caption (the host's tour area).
    Params: $listing (HasMedia model).
--}}
@php
    $photos = ($listing->relationLoaded('media') ? $listing->media : $listing->media()->get())
        ->where('kind', \App\Modules\Marketplace\Models\Media::KIND_IMAGE)
        ->sortBy('sort_order')
        ->map(fn ($m) => ['src' => $m->url(), 'area' => $m->caption ?: 'Highlights', 'alt' => $m->alt ?: $listing->name])
        ->unique('src')->values();
    $areas = $photos->pluck('area')->unique()->values();
@endphp

@if ($photos->count() > 1)
<section id="tour" class="tour" aria-labelledby="tour-title"
    x-data="{
        photos: @js($photos),
        area: 'All',
        open: false,
        i: 0,
        touchX: null,
        get shown() { return this.area === 'All' ? this.photos : this.photos.filter(p => p.area === this.area) },
        show(index) { this.i = index; this.open = true; document.documentElement.style.overflow = 'hidden'; this.$nextTick(() => this.$refs.close.focus()) },
        close() { this.open = false; document.documentElement.style.overflow = '' },
        step(d) { const n = this.shown.length; this.i = (this.i + d + n) % n },
    }"
    @tour-open.window="area = 'All'; show($event.detail ?? 0)"
    @keydown.window="if (!open) return; if ($event.key === 'Escape') close(); if ($event.key === 'ArrowRight') step(1); if ($event.key === 'ArrowLeft') step(-1)">

    <div class="tour__head">
        <div>
            <p class="tour__eyebrow">Photo tour</p>
            <h2 id="tour-title" class="tour__title">Take a look around</h2>
        </div>
        <p class="tour__count">{{ $photos->count() }} photos · {{ $areas->count() }} areas</p>
    </div>

    <div class="tour__chips" role="tablist" aria-label="Tour areas">
        @foreach ($areas->prepend('All') as $name)
            <button type="button" role="tab" class="tour__chip" :aria-selected="area === @js($name)" :class="{ 'is-active': area === @js($name) }" @click="area = @js($name)">
                {{ $name }}
                <span>{{ $name === 'All' ? $photos->count() : $photos->where('area', $name)->count() }}</span>
            </button>
        @endforeach
    </div>

    <div class="tour__grid">
        <template x-for="(photo, index) in shown" :key="photo.src">
            <button type="button" class="tour__tile" :class="{ 'tour__tile--lead': index === 0 }" @click="show(index)" :aria-label="`Open photo ${index + 1}: ${photo.area}`">
                <img :src="photo.src" :alt="photo.alt" loading="lazy" decoding="async">
                <span class="tour__tag" x-text="photo.area"></span>
            </button>
        </template>
    </div>

    <div class="tour__viewer" x-show="open" x-cloak x-transition.opacity.duration.200ms role="dialog" aria-modal="true" aria-label="Photo tour"
        @touchstart="touchX = $event.touches[0].clientX"
        @touchend="if (touchX !== null) { const dx = $event.changedTouches[0].clientX - touchX; if (Math.abs(dx) > 40) step(dx < 0 ? 1 : -1); touchX = null }">
        <div class="tour__bar">
            <p><strong x-text="shown[i]?.area"></strong> <span x-text="`${i + 1} / ${shown.length}`"></span></p>
            <button type="button" class="tour__close" x-ref="close" @click="close()">Close</button>
        </div>
        <div class="tour__stage" @click.self="close()">
            <button type="button" class="tour__nav tour__nav--prev" @click="step(-1)" aria-label="Previous photo">&larr;</button>
            <img :src="shown[i]?.src" :alt="shown[i]?.alt" class="tour__image">
            <button type="button" class="tour__nav tour__nav--next" @click="step(1)" aria-label="Next photo">&rarr;</button>
        </div>
        <div class="tour__strip">
            <template x-for="(photo, index) in shown" :key="'s' + photo.src">
                <button type="button" @click="i = index" :class="{ 'is-active': index === i }" :aria-label="`Photo ${index + 1}`"><img :src="photo.src" alt="" loading="lazy"></button>
            </template>
        </div>
    </div>
</section>
@endif
