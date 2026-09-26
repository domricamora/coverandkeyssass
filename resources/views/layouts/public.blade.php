<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
        @php
            $seoDescription = $description ?: 'Cover & Keys runs the whole property: stays, restaurants, housekeeping and guests in one hospitality operating system.';
            $seoCanonical = $canonical ?? url()->current();
            $seoImage = $image ? url($image) : null;
            $seoJsonLd = $jsonLd();
            $navLinks = [
                ['Stays', route('marketplace.hotels'), request()->routeIs('marketplace.hotels', 'marketplace.search', 'marketplace.locations.show', 'marketplace.properties.show', 'marketplace.home')],
                ['Restaurants', route('marketplace.restaurants.index'), request()->routeIs('marketplace.restaurants.*')],
                ['For hosts', route('marketing.features'), request()->routeIs('marketing.features')],
                ['Pricing', route('marketing.pricing'), request()->routeIs('marketing.pricing')],
            ];
        @endphp
        <title>{{ $title ? $title.' | '.config('app.name') : config('app.name').' | Stays, restaurants and the system that runs them' }}</title>
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

        <header class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85">
            <div class="flex h-[68px] items-center gap-6 px-5 lg:px-10 2xl:px-16">
                <a class="flex shrink-0 items-center gap-2.5 text-fg" href="{{ route('home') }}" aria-label="{{ config('app.name') }} home">
                    <svg viewBox="0 0 32 32" width="26" height="26" fill="none" aria-hidden="true" class="text-brand">
                        <path d="M16 3.2a12.8 12.8 0 1 1-9.05 21.85" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        <circle cx="16" cy="16" r="8.4" stroke="currentColor" stroke-width="1.1" opacity="0.45"/>
                        <circle cx="16" cy="13.6" r="3" fill="var(--coral)"/>
                        <path d="M16 16.4 14.7 23h2.6L16 16.4Z" fill="var(--coral)"/>
                    </svg>
                    <span class="font-display text-[17px] font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                    @foreach ($navLinks as [$label, $href, $active])
                        <a href="{{ $href }}" @if ($active) aria-current="page" @endif
                           class="px-3 py-2 text-[14px] transition-colors {{ $active ? 'font-medium text-brand' : 'text-fg-2 hover:text-fg' }}">{{ $label }}</a>
                    @endforeach
                </nav>

                @if ($showSearch)
                    <form class="ml-auto hidden h-11 items-stretch border border-line-strong bg-white xl:flex" method="GET" action="{{ route('marketplace.hotels') }}" role="search">
                        <label class="flex flex-col justify-center px-4">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.1em] text-fg-3">Where</span>
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="Boracay, El Nido, Baguio…" class="w-44 border-0 bg-transparent p-0 text-[13px] text-fg placeholder:text-fg-4 focus:ring-0">
                        </label>
                        <label class="flex flex-col justify-center px-4">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.1em] text-fg-3">Guests</span>
                            <input type="number" name="guests" min="1" max="50" value="{{ request('guests') }}" placeholder="2" class="w-14 border-0 bg-transparent p-0 text-[13px] text-fg placeholder:text-fg-4 focus:ring-0">
                        </label>
                        <button class="flex w-11 items-center justify-center bg-coral text-white transition-colors hover:bg-coral-deep" type="submit" aria-label="Search stays">
                            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        </button>
                    </form>
                @endif

                <div class="ml-auto flex items-center gap-2 {{ $showSearch ? 'xl:ml-0' : '' }}">
                    <a href="{{ route('register') }}" class="hidden px-3 py-2 text-[14px] font-medium text-fg-2 hover:text-brand md:block">List your property</a>

                    @auth
                        <details class="relative">
                            <summary class="flex h-10 cursor-pointer list-none items-center gap-2.5 border border-line pl-1.5 pr-3 hover:border-line-strong [&::-webkit-details-marker]:hidden">
                                <span class="flex h-7 w-7 items-center justify-center bg-brand text-xs font-semibold text-white" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="hidden text-[13px] text-fg sm:block">{{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</span>
                            </summary>
                            <div class="absolute right-0 top-12 z-50 w-60 border border-line bg-white py-1 shadow-[0_18px_40px_-18px_rgba(16,37,42,.35)]">
                                <p class="border-b border-line px-4 pb-2.5 pt-2 text-[13px] font-medium text-fg">{{ auth()->user()->name }}<br><span class="font-normal text-fg-3">{{ auth()->user()->email }}</span></p>
                                @foreach ([['My trips', route('account.dashboard')], ['Wish list', route('marketplace.favorites.index')], ['Dashboard', route('dashboard')], ['Businesses', route('tenants.index')], ['Profile', route('profile.edit')]] as [$label, $href])
                                    <a class="block px-4 py-2 text-[13px] text-fg-2 hover:bg-soft hover:text-fg" href="{{ $href }}">{{ $label }}</a>
                                @endforeach
                                @if (auth()->user()->isPlatformAdmin())
                                    <a class="block px-4 py-2 text-[13px] text-fg-2 hover:bg-soft hover:text-fg" href="{{ route('admin.dashboard') }}">Platform admin</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-line pt-1">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-[13px] text-coral-deep hover:bg-soft">Sign out</button>
                                </form>
                            </div>
                        </details>
                    @else
                        <a class="hidden px-3 py-2 text-[14px] text-fg-2 hover:text-fg sm:block" href="{{ route('login') }}">Sign in</a>
                        <a class="hidden h-10 items-center bg-brand px-4 text-[14px] font-medium text-white transition-colors hover:bg-brand-deep sm:inline-flex" href="{{ route('register') }}">Get started</a>
                    @endauth

                    <button type="button" class="flex h-10 w-10 items-center justify-center border border-line lg:hidden" aria-controls="mobile-nav" aria-expanded="false" :aria-expanded="nav.toString()" @click="nav = ! nav">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <span class="sr-only">Menu</span>
                    </button>
                </div>
            </div>

            <nav id="mobile-nav" x-cloak x-show="nav" x-transition.opacity.duration.150ms class="border-t border-line bg-white px-5 pb-5 pt-2 lg:hidden" aria-label="Mobile">
                @foreach ($navLinks as [$label, $href, $active])
                    <a href="{{ $href }}" class="block border-b border-line py-3 text-[15px] {{ $active ? 'font-medium text-brand' : 'text-fg' }}">{{ $label }}</a>
                @endforeach
                <a href="{{ route('register') }}" class="block border-b border-line py-3 text-[15px] text-fg">List your property</a>
                @guest
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a class="flex h-11 items-center justify-center border border-line-strong text-[14px]" href="{{ route('login') }}">Sign in</a>
                        <a class="flex h-11 items-center justify-center bg-brand text-[14px] font-medium text-white" href="{{ route('register') }}">Get started</a>
                    </div>
                @endguest
            </nav>
        </header>

        <main id="main">
            @if (! empty($announcement))
                <div role="status" class="bg-coral px-4 py-2 text-center text-[14px] font-medium text-white">{{ $announcement }}</div>
            @endif
            @include('layouts.partials.messages')
            @if ($breadcrumbs !== [])
                <nav class="px-5 py-4 lg:px-10 2xl:px-16" aria-label="Breadcrumb">
                    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-fg-3">
                        <li><a class="hover:text-brand" href="{{ route('home') }}">Home</a></li>
                        @foreach ($breadcrumbs as $label => $url)
                            <li class="flex items-center gap-2">
                                <span aria-hidden="true" class="text-fg-4">/</span>
                                @if ($url && ! $loop->last)
                                    <a class="hover:text-brand" href="{{ $url }}">{{ $label }}</a>
                                @else
                                    <span aria-current="page" class="text-fg">{{ $label }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            {{ $slot }}
        </main>

        <footer class="mt-24 border-t border-line bg-raised">
            <div class="grid gap-10 px-5 py-14 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr] lg:px-10 2xl:px-16">
                <div class="max-w-sm">
                    <p class="flex items-center gap-2.5">
                        <svg viewBox="0 0 32 32" width="24" height="24" fill="none" aria-hidden="true" class="text-brand">
                            <path d="M16 3.2a12.8 12.8 0 1 1-9.05 21.85" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                            <circle cx="16" cy="13.6" r="3" fill="var(--coral)"/>
                            <path d="M16 16.4 14.7 23h2.6L16 16.4Z" fill="var(--coral)"/>
                        </svg>
                        <span class="font-display text-[17px] font-semibold text-fg">{{ config('app.name') }}</span>
                    </p>
                    <p class="mt-4 text-[14px] leading-relaxed text-fg-2">Book stays and tables with the people who run them. Hotels, resorts, B&amp;Bs and restaurants run their whole operation on Cover &amp; Keys.</p>
                </div>
                @foreach ([
                    'Explore' => [['All stays', route('marketplace.hotels')], ['Restaurants', route('marketplace.restaurants.index')], ['Search', route('marketplace.search')], ['Wish list', route('marketplace.favorites.index')]],
                    'For hosts' => [['Features', route('marketing.features')], ['Pricing', route('marketing.pricing')], ['List your property', route('register')], ['Contact sales', route('marketing.contact')]],
                ] as $heading => $links)
                    <div>
                        <h2 class="text-[12px] font-semibold uppercase tracking-[0.12em] text-fg-3">{{ $heading }}</h2>
                        <ul class="mt-4 space-y-2.5">
                            @foreach ($links as [$label, $href])
                                <li><a class="text-[14px] text-fg-2 hover:text-brand" href="{{ $href }}">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <div>
                    <h2 class="text-[12px] font-semibold uppercase tracking-[0.12em] text-fg-3">Help</h2>
                    <ul class="mt-4 space-y-2.5">
                        <li><a class="text-[14px] text-fg-2 hover:text-brand" href="{{ route('marketing.contact') }}">Contact</a></li>
                        @foreach ($footerPages ?? [] as $footerPage)
                            <li><a class="text-[14px] text-fg-2 hover:text-brand" href="{{ route('pages.show', $footerPage->slug) }}">{{ $footerPage->title }}</a></li>
                        @endforeach
                        @if (! empty($supportEmail))<li><a class="text-[14px] text-fg-2 hover:text-brand" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>@endif
                        @if (! empty($supportPhone))<li><a class="text-[14px] text-fg-2 hover:text-brand" href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}">{{ $supportPhone }}</a></li>@endif
                        <li><a class="text-[14px] text-fg-2 hover:text-brand" href="{{ route('login') }}">Sign in</a></li>
                    </ul>
                </div>
            </div>
            <div class="flex flex-wrap justify-between gap-3 border-t border-line px-5 py-6 text-[13px] text-fg-3 lg:px-10 2xl:px-16">
                <span>&copy; {{ date('Y') }} {{ config('app.name') }} · All rights reserved</span>
                <span>Prices in Philippine pesos · Pay by card, GCash or Maya</span>
            </div>
        </footer>
    </body>
</html>
