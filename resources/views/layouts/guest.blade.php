<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('layouts.partials.head')
        <title>{{ config('app.name') }} — {{ $title ?? 'Welcome' }}</title>
    </head>
    <body class="h-full dash-body">
        <div class="flex min-h-full flex-col">
            <header class="site-header">
                <div class="container nav">
                    <a class="brand" href="{{ route('home') }}" aria-label="{{ config('app.name') }} home">
                        <span class="brand__mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" width="26" height="26" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16 3.2a12.8 12.8 0 1 1-9.05 21.85" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                                <circle cx="16" cy="16" r="8.4" stroke="currentColor" stroke-width="1.1" opacity="0.45"/>
                                <circle cx="16" cy="13.6" r="3" fill="currentColor"/>
                                <path d="M16 16.4 14.7 23h2.6L16 16.4Z" fill="currentColor"/>
                            </svg>
                        </span>
                        <span class="brand__name">{{ config('app.name') }}</span>
                    </a>
                    <nav class="nav-user">
                        @if (Route::has('login'))
                            @auth
                                <a class="btn btn-ghost btn-sm" href="{{ route('dashboard') }}">Dashboard</a>
                            @else
                                <a class="nav-user__link" href="{{ route('login') }}">Sign in</a>
                                @if (Route::has('register'))
                                    <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Sign up</a>
                                @endif
                            @endauth
                        @endif
                    </nav>
                </div>
            </header>

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="site-footer" style="border-top:1px solid var(--border);padding:18px 0;text-align:center;font-size:.8rem;color:var(--text-3);">
                <div class="container">&copy; {{ date('Y') }} {{ config('app.name') }} &middot; Foundation</div>
            </footer>
        </div>
    </body>
</html>
