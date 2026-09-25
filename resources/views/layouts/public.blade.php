<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
        @php
            $seoDescription = $description ?: 'Cover & Keys runs the whole property: stays, restaurants, housekeeping and guests in one hospitality operating system.';
            $seoCanonical = $canonical ?? url()->current();
            $seoImage = $image ? url($image) : null;
            $seoJsonLd = $jsonLd();
        @endphp
        <title>{{ $title ? $title.' | '.config('app.name') : config('app.name').' | Hospitality operating system' }}</title>
        <meta name="description" content="{{ $seoDescription }}">
        @if ($noindex)<meta name="robots" content="noindex, follow">@endif
        <link rel="canonical" href="{{ $seoCanonical }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoCanonical }}">
        <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
        <meta name="twitter:card" content="{{ $seoImage ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        @if ($seoImage)
            <meta property="og:image" content="{{ $seoImage }}">
            <meta name="twitter:image" content="{{ $seoImage }}">
        @endif
        @if ($seoJsonLd)<script type="application/ld+json">{!! $seoJsonLd !!}</script>@endif
    </head>
    <body class="public-body" x-data="{ nav: false }" @keydown.escape.window="nav = false">
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

                <button type="button" class="nav-menu-btn" aria-controls="mobile-nav" aria-expanded="false" :aria-expanded="nav.toString()" @click="nav = ! nav">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <span class="sr-only">Menu</span>
                </button>

                @auth
                    <details class="menu">
                        <summary class="menu__btn">
                            <span class="avatar avatar--sm" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        </summary>
                        <div class="menu__panel">
                            <p class="menu__head">{{ auth()->user()->name }}<br><small>{{ auth()->user()->email }}</small></p>
                            <hr>
                            <a href="{{ route('account.dashboard') }}">My trips</a>
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
            <nav class="mobile-nav" id="mobile-nav" :class="{ 'is-open': nav }" aria-label="Mobile">
                <a href="{{ route('marketplace.hotels') }}">Stays</a>
                <a href="{{ route('marketplace.restaurants.index') }}">Restaurants</a>
                <a href="{{ route('marketing.features') }}">Features</a>
                <a href="{{ route('marketing.pricing') }}">Pricing</a>
                @guest
                    <a href="{{ route('login') }}">Sign in</a>
                    <a class="btn btn-primary" href="{{ route('register') }}">Get started</a>
                @endguest
            </nav>
        </header>

        <main id="main">
            @if (! empty($announcement))
                <div role="status" style="background:var(--gold);color:#1a1a1a;text-align:center;padding:8px 16px;font-weight:500;">{{ $announcement }}</div>
            @endif
            @include('layouts.partials.messages')
            @if ($breadcrumbs !== [])
                <nav class="crumbs container" aria-label="Breadcrumb">
                    <ol>
                        <li><a href="{{ route('home') }}">Home</a></li>
                        @foreach ($breadcrumbs as $label => $url)
                            <li>
                                @if ($url && ! $loop->last)
                                    <a href="{{ $url }}">{{ $label }}</a>
                                @else
                                    <span aria-current="page">{{ $label }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
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
                    <p class="footer-tag">The hospitality operating system for hotels, resorts and restaurants. Marketplace, front desk and back office in one platform.</p>
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
                        @foreach ($footerPages ?? [] as $footerPage)
                            <li><a href="{{ route('pages.show', $footerPage->slug) }}">{{ $footerPage->title }}</a></li>
                        @endforeach
                        @if (! empty($supportEmail))<li><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>@endif
                        @if (! empty($supportPhone))<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}">{{ $supportPhone }}</a></li>@endif
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
