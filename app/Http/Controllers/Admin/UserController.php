<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('roles:id,slug,display_name')
            ->when($request->string('q'), function ($query, $q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            // Hosts belong to a business; customers don't (Phase 28).
            ->when($request->query('type') === 'hosts', fn ($q) => $q->whereHas('tenants'))
            ->when($request->query('type') === 'customers', fn ($q) => $q->whereDoesntHave('tenants')->whereDoesntHave('roles', fn ($r) => $r->where('slug', 'super_admin')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function suspend(Request $request, User $user)
    {
        if ($user->isPlatformAdmin()) {
            return back()->with('error', 'Platform administrators cannot be suspended.');
        }

        $old = ['status' => $user->status];
        $user->update(['status' => 'suspended']);

        // Revoke sessions immediately.
        \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        app(AuditLogger::class)->log('platform.user.suspended', $user, $old, ['status' => 'suspended']);

        return back()->with('success', __('User suspended.'));
    }

    public function activate(Request $request, User $user)
    {
        $old = ['status' => $user->status];
        $user->update(['status' => 'active']);

        app(AuditLogger::class)->log('platform.user.activated', $user, $old, ['status' => 'active']);

        return back()->with('success', __('User activated.'));
    }
}
