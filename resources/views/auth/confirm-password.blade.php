<x-guest-layout>
    <div class="auth-wrap">
        <div class="auth-card card">
            <h1>Confirm password</h1>
            <p class="auth-card__sub">This is a secure area. Please confirm your password before continuing.</p>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <button class="btn btn-primary btn-block" type="submit">Confirm</button>
            </form>
        </div>
    </div>
</x-guest-layout>