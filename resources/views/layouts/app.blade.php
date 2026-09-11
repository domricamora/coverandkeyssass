<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('layouts.partials.head')
        <title>{{ config('app.name') }} — {{ $title ?? 'Dashboard' }}</title>
    </head>
    <body class="h-full dash-body">
        <a class="skip-link" href="#main">Skip to content</a>

        <header class="site-header">
            <div class="container nav nav--slim">
                <a class="brand" href="{{ route('home') }}" aria-label="{{ config('app.name') }} home">
                    <span class="brand__mark" aria-hidden="true">
                        <svg viewBox="0 0 28 28" width="22" height="22" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="1" y="1" width="26" height="26" rx="7" fill="var(--gold)"/>
                            <path d="M9 9h6a3 3 0 0 1 0 6H9V9Zm2 2v2h4a1 1 0 0 0 0-2h-4Zm0 6v4h-2v-4h2Zm4 0h4a3 3 0 0 1 0 6h-4v-6Zm2 2v2h2a1 1 0 0 0 0-2h-2Z" fill="var(--ink-900)"/>
                        </svg>
                    </span>
                    <span class="brand__name">{{ config('app.name') }}</span>
                </a>

                <div class="nav-user">
                    @if (auth()->user()->isPlatformAdmin())
                        <a class="nav-user__link" href="{{ route('admin.dashboard') }}">Admin</a>
                    @endif
                    <a class="nav-user__link" href="{{ route('tenants.index') }}">Businesses</a>
                    <details class="menu">
                        <summary class="menu__btn">
                            <span class="avatar avatar--sm" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        </summary>
                        <div class="menu__panel">
                            <p class="menu__head">{{ auth()->user()->name }}<br><small>{{ auth()->user()->email }}</small></p>
                            <hr>
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                            <a href="{{ route('tenants.index') }}">Businesses</a>
                            <a href="{{ route('profile.edit') }}">Profile</a>
                            <hr>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="menu__logout">Sign out</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </header>

        <div class="dash container">
            <aside class="dash__side">
                <nav class="side-nav">
                    @include('layouts.partials.sidebar-links')
                </nav>
            </aside>
            <section class="dash__main" id="main">
                @include('layouts.partials.messages')
                {{ $slot }}
            </section>
        </div>
    </body>
</html>