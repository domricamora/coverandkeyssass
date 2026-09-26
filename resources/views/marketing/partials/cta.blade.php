{{-- Closing call to action shared by the marketing pages. --}}
<section class="mt-24 bg-coral-soft">
    <div class="flex flex-wrap items-center justify-between gap-8 px-5 py-16 lg:px-10 2xl:px-16">
        <div class="max-w-2xl">
            <h2 class="font-display text-[clamp(1.8rem,3vw,2.6rem)] font-medium tracking-[-0.015em] text-fg">Open your doors on Cover &amp; Keys</h2>
            <p class="mt-3 text-[16px] leading-relaxed text-fg-2">Create an account, add your property or restaurant and publish your first listing today. Team roles and the audit trail are ready from day one.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="inline-flex h-12 items-center bg-coral px-6 text-[15px] font-semibold text-white transition-colors hover:bg-coral-deep">List your property</a>
            <a href="{{ route('marketing.contact') }}" class="inline-flex h-12 items-center border border-line-strong bg-white px-6 text-[15px] font-medium text-fg transition-colors hover:border-brand hover:text-brand">Talk to us</a>
        </div>
    </div>
</section>
