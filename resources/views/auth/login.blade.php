<x-guest-layout>
    <div class="auth-wrap">
        <div class="auth-card card">
            <h1>Log in</h1>
            <p class="auth-card__sub">Welcome back to {{ config('app.name') }}.</p>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div class="mt-4">
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <label class="inline-flex items-center gap-2 mt-4" style="color:var(--text-2)">
                    <input id="remember_me" type="checkbox" name="remember" style="accent-color:var(--gold)">
                    <span class="text-sm">Remember me</span>
                </label>

                <button class="btn btn-primary btn-block" type="submit">Log in</button>

                <div class="flex items-center justify-between mt-4">
                    @if (Route::has('password.request'))
                        <a class="text-sm underline" style="color:var(--text-3)" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                    @endif
                </div>
            </form>

            <p class="auth-card__alt">New here? <a href="{{ route('register') }}">Create an account</a></p>
        </div>
    </div>
</x-guest-layout>