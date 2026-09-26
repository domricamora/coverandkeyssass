<x-public-layout :title="$title" description="Talk to the Cover & Keys team about onboarding your hotel, resort or restaurant, module set-up and moving your data across." :show-search="false" :breadcrumbs="['Contact' => null]">
    @php
        $pad = 'px-5 lg:px-10 2xl:px-16';
        $channels = [
            ['Sales & onboarding', 'Module set-up, a walkthrough and moving your data across.', $contact['sales_email'] ? ['mailto:'.$contact['sales_email'], $contact['sales_email']] : null, 'CONTACT_SALES_EMAIL'],
            ['Support', 'Account access, roles, billing questions and anything urgent.', $contact['support_email'] ? ['mailto:'.$contact['support_email'], $contact['support_email']] : null, 'CONTACT_SUPPORT_EMAIL'],
            ['Phone & office', $contact['address'] ?: 'Call during office hours, Philippine time.', $contact['phone'] ? ['tel:'.preg_replace('/[^0-9+]/', '', $contact['phone']), $contact['phone']] : null, 'CONTACT_PHONE / CONTACT_ADDRESS'],
        ];
    @endphp

    <section class="grid lg:grid-cols-[1.1fr_1fr]">
        <div class="{{ $pad }} pb-16 pt-12 lg:pt-16">
            <p class="text-[13px] font-semibold uppercase tracking-[0.14em] text-coral-deep">Contact</p>
            <h1 class="mt-3 max-w-2xl font-display text-[clamp(2.3rem,4.6vw,4rem)] font-medium leading-[1.04] tracking-[-0.02em] text-fg">Let's get your place set up.</h1>
            <p class="mt-5 max-w-xl text-[17px] leading-relaxed text-fg-2">We work with hotels, resorts, guesthouses and restaurants across the Philippines. Tell us a little about your place and we'll help you switch on the right modules.</p>

            <div class="mt-10 grid gap-px border border-line bg-line sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                @foreach ($channels as [$heading, $body, $link, $env])
                    <div class="bg-white p-6">
                        <h2 class="text-[16px] font-semibold text-fg">{{ $heading }}</h2>
                        <p class="mt-2 text-[14px] leading-relaxed text-fg-2">{{ $body }}</p>
                        @if ($link)
                            <a href="{{ $link[0] }}" class="mt-4 inline-block text-[15px] font-medium text-brand hover:text-brand-deep">{{ $link[1] }}</a>
                        @elseif (app()->isLocal())
                            <p class="mt-4 text-[13px] text-fg-3">Set <code>{{ $env }}</code> in .env to publish this.</p>
                        @else
                            <p class="mt-4 text-[14px] text-fg-3">Coming soon.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <h2 class="mt-12 text-[13px] font-semibold uppercase tracking-[0.12em] text-fg-3">What helps us help you faster</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ([
                    'Your property type and how many rooms or tables you run',
                    'What you want covered first: front desk, restaurant or housekeeping',
                    'How many staff need a login, and their roles',
                    'Any data to bring across: rooms, guests, bookings',
                ] as $item)
                    <li class="flex gap-3 text-[15px] text-fg">
                        <svg width="18" height="18" fill="none" stroke="var(--primary)" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" class="mt-0.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>

            <div class="mt-12 flex flex-wrap gap-3">
                <a class="inline-flex h-12 items-center bg-brand px-6 text-[15px] font-semibold text-white transition-colors hover:bg-brand-deep" href="{{ route('register') }}">Create your account</a>
                <a class="inline-flex h-12 items-center border border-line-strong bg-white px-6 text-[15px] font-medium text-fg transition-colors hover:border-brand hover:text-brand" href="{{ route('marketplace.hotels') }}">Browse the marketplace</a>
            </div>
        </div>
        <div class="relative hidden min-h-[520px] overflow-hidden lg:block">
            <img src="{{ asset('img/demo/tour/lobby-5-5jvIpxkKEVs.jpg') }}" alt="A bright hotel lounge" class="absolute inset-0 h-full w-full object-cover">
        </div>
    </section>
</x-public-layout>
