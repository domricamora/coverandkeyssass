<?php

namespace App\Http\Middleware;

use App\Support\DashboardNav;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia (React dashboard): root view + props every screen gets — the
 * signed-in user, current business, grouped sidebar and flash messages.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app-react';

    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = app(TenantContext::class);

        return [
            ...parent::share($request),
            'app' => ['name' => config('app.name'), 'home' => route('home')],
            'guestUrls' => fn () => $user ? null : ['identify' => route('guest.identify'), 'password' => route('guest.password'), 'link' => route('login.link.send')],
            'auth' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'business' => fn () => $tenant->has() ? $tenant->tenant()->name : null,
            'nav' => fn () => $user ? DashboardNav::for($user, (string) $request->route()?->getName()) : [],
            'flash' => fn () => array_filter([
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ]),
            'links' => fn () => $user ? array_filter([
                'logout' => route('logout'),
                'profile' => route('profile.edit'),
                'trips' => route('account.dashboard'),
                'businesses' => route('tenants.index'),
                'admin' => $user->isPlatformAdmin() ? route('admin.dashboard') : null,
            ]) : [],
        ];
    }
}
