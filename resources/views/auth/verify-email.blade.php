<x-guest-layout>
    <div class="auth-wrap">
        <div class="auth-card card">
            <h1>Verify email</h1>
            <p class="auth-card__sub">Thanks for signing up! Before getting started, could you verify your email address by clicking the link we just emailed you?</p>

            @if (session('status') == 'verification-link-sent')
                <div class="flash flash-success mt-4">A new verification link has been sent to the email address you provided during registration.</div>
            @endif

            <div class="flex items-center justify-between mt-6 gap-3">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">Resend verification email</button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Log out</button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>