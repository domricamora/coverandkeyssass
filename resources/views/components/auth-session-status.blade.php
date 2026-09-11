@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flash flash-success']) }}>
        {{ $status }}
    </div>
@endif
