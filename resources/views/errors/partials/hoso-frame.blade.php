<x-guest-layout>
    <div class="mx-auto max-w-lg px-4 py-20 text-center">
        <p class="hoso-badge inline-flex bg-brand-100 text-brand-800 dark:bg-brand-800 dark:text-brand-100">Error {{ $code }}</p>
        <h1 class="mt-4 font-display text-4xl font-semibold text-brand-950 dark:text-sand-50">{{ $title }}</h1>
        <p class="mt-3 text-brand-600 dark:text-brand-300">{{ $message }}</p>
        <a href="{{ route('home') }}" class="hoso-btn-primary mt-8">Back to home</a>
    </div>
</x-guest-layout>
