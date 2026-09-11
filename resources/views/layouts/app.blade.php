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
                        <svg viewBox="0 0 32 32" width="22" height="22" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16 3.2a12.8 12.8 0 1 1-9.05 21.85" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                            <circle cx="16" cy="16" r="8.4" stroke="currentColor" stroke-width="1.1" opacity="0.45"/>
                            <circle cx="16" cy="13.6" r="3" fill="currentColor"/>
                            <path d="M16 16.4 14.7 23h2.6L16 16.4Z" fill="currentColor"/>
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