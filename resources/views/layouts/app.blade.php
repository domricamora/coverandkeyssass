<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        @include('layouts.partials.head')
        <title>{{ $title ?? 'Dashboard' }} | {{ config('app.name') }}</title>
    </head>
    <body class="h-full dash-body" x-data="{ nav: false }" @keydown.escape.window="nav = false">
        <a class="skip-link" href="#main">Skip to content</a>

        <header class="site-header">
            <div class="container nav nav--slim">
                <a class="brand" href="{{ route('home') }}" aria-label="{{ config('app.name') }} home">
                    <span class="brand__mark" aria-hidden="true">
                        <img class="brand-logo" src="{{ asset('img/brand/logo.svg') }}" width="34" height="34" style="border-radius:9999px;border:2px solid #c9a13b;padding:3px;box-sizing:border-box" alt="" aria-hidden="true">
                    </span>
                    <span class="brand__name">{{ config('app.name') }}</span>
                </a>

                <div class="nav-user">
                    <button type="button" class="nav-menu-btn" aria-controls="dash-side" :aria-expanded="nav.toString()" aria-expanded="false" @click="nav = ! nav">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <span>Menu</span>
                    </button>
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
                            <a href="{{ route('account.dashboard') }}">My trips</a>
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
            <aside class="dash__side" id="dash-side" :class="{ 'is-open': nav }">
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