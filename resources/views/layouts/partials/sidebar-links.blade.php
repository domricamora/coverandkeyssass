@php
    $current = app(\App\Support\TenantContext::class);
    $route = request()->route()?->getName();
@endphp

@php($links = [
    ['dashboard', 'Overview', 'M3 12l9-8 9 8M5 10v10h14V10'],
    ['team', 'Team', 'M16 14a4 4 0 10-8 0 4 4 0 008 0zM2 20c1.5-3 5-4 8-4s6.5 1 8 4'],
    ['tenants.settings', 'Settings', 'M12 15a3 3 0 100-6 3 3 0 000 6zM4 12a8 8 0 0116 0'],
])

@foreach ($links as [$name, $label, $path])
    <a href="{{ route($name) }}"
       @class([
           'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
           'bg-brand-600 text-white shadow-sm' => $route === $name,
           'text-brand-700 hover:bg-sand-100 dark:text-sand-200 dark:hover:bg-brand-800' => $route !== $name,
       ])>
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
        {{ $label }}
    </a>
@endforeach
