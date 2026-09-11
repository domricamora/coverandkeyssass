@props(['active'])

@php
$classes = ($active ?? false)
            ? 'is-active'
            : '';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
