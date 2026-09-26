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
                            <img class="brand-logo" src="{{ asset('img/brand/logo.svg') }}" width="38" height="38" style="border-radius:9999px;border:2px solid #c9a13b;padding:3px;box-sizing:border-box" alt="" aria-hidden="true">
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
