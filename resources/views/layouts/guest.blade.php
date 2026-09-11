<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('layouts.partials.head')
        <title>{{ config('app.name', 'Hospitality OS') }} — {{ $title ?? 'Welcome' }}</title>
    </head>
    <body class="h-full bg-sand-50 font-sans text-brand-950 antialiased dark:bg-brand-950 dark:text-sand-100">
        <div class="flex min-h-full flex-col">
            <header class="border-b border-sand-200 dark:border-brand-800">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                    <a href="{{ route('home') }}" class="flex items-center gap-2">
                        <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-display text-base font-bold text-white">H</span>
                        <span class="font-display text-lg font-semibold tracking-tight text-brand-900 dark:text-sand-50">Hospitality OS</span>
                    </a>
                    <nav class="flex items-center gap-3 text-sm font-medium">
                        <a href="{{ route('login') }}" class="text-brand-700 underline-offset-4 hover:underline dark:text-brand-300">Log in</a>
                        <a href="{{ route('register') }}" class="hoso-btn-primary h-9 px-4">Create account</a>
                    </nav>
                </div>
            </header>

            <main class="flex-1">
                @include('layouts.partials.messages')
                {{ $slot }}
            </main>

            <footer class="border-t border-sand-200 px-6 py-4 text-xs text-brand-500 dark:border-brand-800 dark:text-brand-400">
                {{ config('app.name') }} · Laravel {{ app()->version() }}
            </footer>
        </div>
    </body>
</html>
