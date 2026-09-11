<x-guest-layout>
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <div>
                <p class="hoso-badge mb-4 inline-flex bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">Operating System for Hospitality</p>
                <h1 class="font-display text-4xl font-semibold tracking-tight text-brand-950 sm:text-5xl dark:text-sand-50">
                    One system. Every property. Total control.
                </h1>
                <p class="mt-5 max-w-xl text-lg text-brand-700 dark:text-brand-200">
                    Hospitality OS is the operating platform for hotels, resorts and guesthouses —
                    properties, reservations, housekeeping, and your team, unified in one place.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ route('register') }}" class="hoso-btn-primary h-11 px-6">Get started</a>
                    <a href="{{ route('login') }}" class="hoso-btn-secondary h-11 px-6">I already have an account</a>
                </div>
                <dl class="mt-12 grid max-w-lg grid-cols-3 gap-4 text-sm">
                    <div class="rounded-lg border border-sand-200 bg-white p-4 dark:border-brand-800 dark:bg-brand-900">
                        <dt class="font-display text-2xl font-semibold text-brand-900 dark:text-sand-50">01</dt>
                        <dd class="mt-1 text-brand-600 dark:text-brand-300">Foundation</dd>
                    </div>
                    <div class="rounded-lg border border-sand-200 bg-white p-4 dark:border-brand-800 dark:bg-brand-900">
                        <dt class="font-display text-2xl font-semibold text-brand-900 dark:text-sand-50">RBAC</dt>
                        <dd class="mt-1 text-brand-600 dark:text-brand-300">Granular roles</dd>
                    </div>
                    <div class="rounded-lg border border-sand-200 bg-white p-4 dark:border-brand-800 dark:bg-brand-900">
                        <dt class="font-display text-2xl font-semibold text-brand-900 dark:text-sand-50">24/7</dt>
                        <dd class="mt-1 text-brand-600 dark:text-brand-300">Audit trail</dd>
                    </div>
                </dl>
            </div>
            <div class="hidden lg:block">
                <div class="rounded-2xl border border-sand-200 bg-white p-8 shadow-sm dark:border-brand-800 dark:bg-brand-900">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-brand-500 dark:text-brand-300">Built for</p>
                    <ul class="mt-4 space-y-3 text-brand-800 dark:text-sand-100">
                        @foreach (['Hotels', 'Resorts', 'Bed & Breakfasts', 'Guesthouses', 'Vacation rentals', 'Hostels'] as $type)
                            <li class="flex items-center gap-3">
                                <svg class="h-4 w-4 text-brand-600 dark:text-brand-300" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ $type }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>
</x-guest-layout>
