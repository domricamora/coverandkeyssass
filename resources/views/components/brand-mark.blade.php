@props(['size' => 36])
{{-- The gold-rimmed logo on its teal plate (styles in resources/css/motion.css; same mark as BrandMark in react/ui.jsx). --}}
<span class="brand-plate" style="width:{{ $size }}px;height:{{ $size }}px" aria-hidden="true"><img class="brand-logo" src="{{ asset('img/brand/logo.svg') }}" width="{{ $size }}" height="{{ $size }}" alt=""></span>
