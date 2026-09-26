<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\LoginLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checkout without a registration step (Booking.com / Agoda style).
 *
 * The booking, order and table steps collect name + email + phone. A new
 * email gets an account created quietly and is signed in, so the existing
 * signed-in endpoints (reserve, checkout, pay) work unchanged; the guest
 * can set a password later via "forgot password". An email that already
 * has an account is never signed in on the email alone: the guest types
 * the password or asks for a sign-in link.
 */
class GuestCheckoutController extends Controller
{
    public function identify(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160'],
        ]);

        if ($request->user()) {
            return $this->signedIn($request);
        }

        if (User::query()->where('email', Str::lower($data['email']))->exists()) {
            return response()->json(['status' => 'existing']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => Hash::make(Str::random(40)),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->signedIn($request);
    }

    public function password(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt(['email' => Str::lower($data['email']), 'password' => $data['password']])) {
            throw ValidationException::withMessages(['password' => 'That password does not match this email.']);
        }

        $request->session()->regenerate();

        return $this->signedIn($request);
    }

    /** Email a 30-minute sign-in link that returns to `to`. Same answer whether or not the email exists. */
    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'to' => ['nullable', 'string', 'max:500']]);

        $user = User::query()->where('email', Str::lower($data['email']))->first();

        if ($user) {
            $user->notify(new LoginLink(URL::temporarySignedRoute('login.link', now()->addMinutes(30), [
                'user' => $user->id,
                'to' => self::safePath($data['to'] ?? '/'),
            ])));
        }

        return response()->json(['sent' => true]);
    }

    /** GET /login-link/{user} (signed): possession of the email proves identity. */
    public function useLink(Request $request, User $user)
    {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect(self::safePath((string) $request->query('to')));
    }

    /** Only same-site relative paths ("/cart"), never "//evil" or "https://…". */
    public static function safePath(string $to): string
    {
        return preg_match('#^/(?![/\\\\])#', $to) ? $to : '/';
    }

    private function signedIn(Request $request)
    {
        return response()->json(['status' => 'signed_in', 'name' => $request->user()->name, 'csrf' => csrf_token()]);
    }
}
