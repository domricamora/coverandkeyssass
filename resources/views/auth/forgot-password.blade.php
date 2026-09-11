<x-guest-layout>
    <div class="auth-wrap">
        <div class="auth-card card">
            <h1>Forgot password</h1>
            <p class="auth-card__sub">Enter your email and we'll send you a reset link.</p>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <button class="btn btn-primary btn-block" type="submit">Email password reset link</button>
            </form>

            <p class="auth-card__alt"><a href="{{ route('login') }}">Back to log in</a></p>
        </div>
    </div>
</x-guest-layout>