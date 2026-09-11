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
                            <svg viewBox="0 0 28 28" width="26" height="26" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1" y="1" width="26" height="26" rx="7" fill="var(--gold)"/>
                                <path d="M9 9h6a3 3 0 0 1 0 6H9V9Zm2 2v2h4a1 1 0 0 0 0-2h-4Zm0 6v4h-2v-4h2Zm4 0h4a3 3 0 0 1 0 6h-4v-6Zm2 2v2h2a1 1 0 0 0 0-2h-2Z" fill="var(--ink-900)"/>
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
