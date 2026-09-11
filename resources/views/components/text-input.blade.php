@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'form-input focus-visible:ring-sky-600 focus-visible:border-sky-600']) }}>
