<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('layouts.partials.head')
        <title>{{ config('app.name', 'Hospitality OS') }} — {{ $title ?? 'Dashboard' }}</title>
    </head>
    <body class="h-full bg-sand-50 font-sans text-brand-950 antialiased dark:bg-brand-950 dark:text-sand-100">
        <div class="min-h-screen lg:flex">
            <aside class="hidden w-64 shrink-0 flex-col border-r border-sand-200 bg-white dark:border-brand-800 dark:bg-brand-900 lg:flex" aria-label="Primary">
                <a href="{{ route('home') }}" class="flex items-center gap-3 px-6 py-6">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 font-display text-lg font-bold text-white">H</span>
                    <span class="font-display text-lg font-semibold tracking-tight text-brand-900 dark:text-sand-50">Hospitality OS</span>
                </a>

                @if (app(\App\Support\TenantContext::class)->has())
                    <div class="mx-4 mb-4 rounded-lg border border-sand-200 bg-sand-50 px-4 py-3 dark:border-brand-800 dark:bg-brand-950">
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-300">Business</p>
                        <p class="truncate font-display text-base font-semibold text-brand-900 dark:text-sand-50">{{ app(\App\Support\TenantContext::class)->tenant()->name }}</p>
                        <a href="{{ route('tenants.index') }}" class="mt-1 inline-block text-xs font-medium text-brand-600 underline-offset-4 hover:underline dark:text-brand-300">Switch business</a>
                    </div>

                    <nav class="flex-1 space-y-1 px-3">
                        @include('layouts.partials.sidebar-links')
                    </nav>
                @endif

                <div class="border-t border-sand-200 px-3 py-4 dark:border-brand-800">
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-brand-800 hover:bg-sand-100 dark:text-sand-100 dark:hover:bg-brand-800">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700 dark:bg-brand-800 dark:text-brand-200">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="min-w-0">
                            <span class="block truncate font-semibold">{{ auth()->user()->name }}</span>
                            <span class="block truncate text-xs text-brand-500 dark:text-brand-400">{{ auth()->user()->email }}</span>
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="mt-1">
                        @csrf
                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-brand-600 hover:bg-sand-100 dark:text-brand-300 dark:hover:bg-brand-800">Sign out</button>
                    </form>
                </div>
            </aside>
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 border-b border-sand-200 bg-sand-50/90 backdrop-blur dark:border-brand-800 dark:bg-brand-950/90">
                    <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                        <div class="flex items-center gap-3 lg:hidden">
                            <button x-data @click="$dispatch('toggle-mobile-nav')" class="grid h-9 w-9 place-items-center rounded-lg border border-sand-200 bg-white text-brand-700 dark:border-brand-700 dark:bg-brand-900 dark:text-sand-100" aria-label="Open menu">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </button>
                            <a href="{{ route('home') }}" class="font-display text-base font-semibold text-brand-900 dark:text-sand-50">Hospitality OS</a>
                        </div>
                        <div class="hidden items-center gap-2 lg:flex">
                            @if (app(\App\Support\TenantContext::class)->has())
                                <span class="hoso-badge bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">{{ app(\App\Support\TenantContext::class)->tenant()->business_type }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if (auth()->user()->isPlatformAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="hoso-btn-secondary h-9 px-3 text-xs">Super Admin</a>
                            @endif
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button type="button" class="grid h-9 w-9 place-items-center rounded-full bg-brand-600 text-sm font-bold text-white" aria-label="Account menu">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); document.getElementById('logout-form-top').submit();">{{ __('Log Out') }}</x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                </header>

                <div x-data="{ open: false }" @toggle-mobile-nav.window="open = !open" class="lg:hidden">
                    <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-brand-950/40" @click="open = false"></div>
                    <div x-show="open" x-cloak x-transition class="fixed inset-y-0 left-0 z-50 w-64 overflow-y-auto border-r border-sand-200 bg-white p-4 dark:border-brand-800 dark:bg-brand-900" aria-label="Mobile navigation">
                        <a href="{{ route('home') }}" class="mb-4 flex items-center gap-2 px-2">
                            <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-display text-base font-bold text-white">H</span>
                            <span class="font-display text-base font-semibold dark:text-sand-50">Hospitality OS</span>
                        </a>
                        @if (app(\App\Support\TenantContext::class)->has())
                            <nav class="space-y-1">
                                @include('layouts.partials.sidebar-links')
                            </nav>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" id="logout-form-top" class="mt-4 border-t border-sand-200 pt-3 dark:border-brand-800">
                            @csrf
                            <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-brand-600 hover:bg-sand-100 dark:text-brand-300 dark:hover:bg-brand-800">Sign out</button>
                        </form>
                    </div>
                </div>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    @include('layouts.partials.messages')
                    {{ $slot }}
                </main>

                <footer class="border-t border-sand-200 px-6 py-4 text-xs text-brand-500 dark:border-brand-800 dark:text-brand-400">
                    {{ config('app.name') }} · Foundation (Phase 01) · Laravel {{ app()->version() }}
                </footer>
            </div>
        </div>
    </body>
</html>
