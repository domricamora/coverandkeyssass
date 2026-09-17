<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
        <title>{{ $title ? $title.' — '.config('app.name') : config('app.name').' — Hospitality operating system' }}</title>
        <meta name="description" content="{{ $description ?? 'Cover & Keys runs the whole property: stays, restaurants, housekeeping and guests in one hospitality operating system.' }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta name="twitter:card" content="summary_large_image">
        <link rel="canonical" href="{{ url()->current() }}">
    </head>
    <body class="public-body">
        <a class="skip-link" href="#main">Skip to content</a>

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

                <nav class="nav-user nav-links" aria-label="Main">
                    <a class="nav-user__link {{ request()->routeIs('marketplace.hotels', 'marketplace.search', 'marketplace.locations.show') ? 'is-active' : '' }}" href="{{ route('marketplace.hotels') }}">Stays</a>
                    <a class="nav-user__link {{ request()->routeIs('marketplace.restaurants.*') ? 'is-active' : '' }}" href="{{ route('marketplace.restaurants.index') }}">Restaurants</a>
                    <a class="nav-user__link {{ request()->routeIs('marketing.features') ? 'is-active' : '' }}" href="{{ route('marketing.features') }}">Features</a>
                    <a class="nav-user__link {{ request()->routeIs('marketing.pricing') ? 'is-active' : '' }}" href="{{ route('marketing.pricing') }}">Pricing</a>
                </nav>

                @if ($showSearch)
                    <form class="search-pill" method="GET" action="{{ route('marketplace.hotels') }}" role="search">
                        <div class="search-pill__seg">
                            <label for="nav-q">Where</label>
                            <input id="nav-q" type="search" name="q" value="{{ request('q') }}" placeholder="Boracay, Cebu, Baguio…">
                        </div>
                        <div class="search-pill__divider" aria-hidden="true"></div>
                        <div class="search-pill__seg" style="max-width:104px;">
                            <label for="nav-guests">Guests</label>
                            <input id="nav-guests" type="number" name="guests" min="1" max="50" value="{{ request('guests') }}" placeholder="2">
                        </div>
                        <button class="search-pill__btn" type="submit" aria-label="Search stays">
                            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        </button>
                    </form>
                @endif

                <a class="nav-search-mobile" href="{{ route('marketplace.hotels') }}" aria-label="Search stays">
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                </a>

                @auth
                    <details class="menu">
                        <summary class="menu__btn">
                            <span class="avatar avatar--sm" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        </summary>
                        <div class="menu__panel">
                            <p class="menu__head">{{ auth()->user()->name }}<br><small>{{ auth()->user()->email }}</small></p>
                            <hr>
                            <a href="{{ route('marketplace.favorites.index') }}">Wish list</a>
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                            @if (auth()->user()->isPlatformAdmin())
                                <a href="{{ route('admin.dashboard') }}">Platform admin</a>
                            @endif
                            <a href="{{ route('tenants.index') }}">Businesses</a>
                            <a href="{{ route('profile.edit') }}">Profile</a>
                            <hr>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="menu__logout">Sign out</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a class="nav-user__link" href="{{ route('login') }}">Sign in</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Get started</a>
                @endauth
            </div>
        </header>

        <main id="main">
            @include('layouts.partials.messages')
            {{ $slot }}
        </main>

        <footer class="site-footer">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand">
                        <span class="brand__mark" style="color:var(--gold);" aria-hidden="true">
                            <svg viewBox="0 0 32 32" width="24" height="24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M16 3.2a12.8 12.8 0 1 1-9.05 21.85" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                                <circle cx="16" cy="13.6" r="3" fill="currentColor"/>
                                <path d="M16 16.4 14.7 23h2.6L16 16.4Z" fill="currentColor"/>
                            </svg>
                        </span>
                        <span class="brand__name">{{ config('app.name') }}</span>
                    </div>
                    <p class="footer-tag">The hospitality operating system for hotels, resorts and restaurants — marketplace, front desk and back office in one platform.</p>
                </div>
                <div>
                    <h2>Explore</h2>
                    <ul>
                        <li><a href="{{ route('marketplace.hotels') }}">All stays</a></li>
                        <li><a href="{{ route('marketplace.restaurants.index') }}">Restaurants</a></li>
                        <li><a href="{{ route('marketplace.search') }}">Search</a></li>
                    </ul>
                </div>
                <div>
                    <h2>Platform</h2>
                    <ul>
                        <li><a href="{{ route('marketing.features') }}">Features</a></li>
                        <li><a href="{{ route('marketing.pricing') }}">Pricing</a></li>
                        <li><a href="{{ route('marketing.contact') }}">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h2>Account</h2>
                    <ul>
                        <li><a href="{{ route('marketplace.favorites.index') }}">Wish list</a></li>
                        <li><a href="{{ route('login') }}">Sign in</a></li>
                        <li><a href="{{ route('register') }}">Create account</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom container">
                <span>&copy; {{ date('Y') }} {{ config('app.name') }} &middot; All rights reserved</span>
                <span>Built for hotels, resorts, B&amp;Bs and restaurants</span>
            </div>
        </footer>
    </body>
</html>
