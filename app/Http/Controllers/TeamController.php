<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Billing\Support\Usage;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Team members of the active business (React screen; replaced the
 * Livewire TeamManager): add by email, change role, remove. Owners and
 * anyone with team.manage (policy `manageTeam`).
 */
class TeamController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $tenant = $this->tenant();

        $roles = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.tenant_id', $tenant->id)->pluck('roles.slug', 'user_roles.user_id');

        return Inertia::render('Team/Index', [
            'business' => $tenant->name,
            'members' => $tenant->tenantUsers()->with('user:id,name,email')->orderBy('created_at')->get()->map(fn ($tu) => [
                'id' => $tu->user_id,
                'name' => $tu->user?->name,
                'email' => $tu->user?->email,
                'role' => $roles[$tu->user_id] ?? null,
                'me' => $tu->user_id === $request->user()->id,
                'urls' => ['role' => route('team.role', $tu->user_id), 'remove' => route('team.remove', $tu->user_id)],
            ]),
            'roles' => $tenant->roles()->orderBy('id')->get(['slug', 'display_name'])->map(fn ($r) => [$r->slug, $r->display_name]),
            'urls' => ['add' => route('team.store')],
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $this->tenant();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in($tenant->roles()->pluck('slug')->all())],
        ]);
        $email = Str::lower($data['email']);

        if ($tenant->users()->where('users.email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This person is already on the team.']);
        }

        Usage::ensureRoom($tenant, 'staff', 1, 'email');

        $user = User::withTrashed()->where('email', $email)->first();
        $user?->trashed() && $user->restore();

        // New platform account with a generated password; the member sets one via "Forgot password".
        $user ??= User::create(['name' => $data['name'], 'email' => $email, 'password' => Hash::make(Str::password(24)), 'status' => 'active']);

        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        $role = $tenant->roles()->where('slug', $data['role'])->firstOrFail();
        $user->assignTenantRole($tenant, $role);

        $this->audit->log('team.member.added', $user, null, ['tenant_id' => $tenant->id, 'role' => $role->slug]);

        return back()->with('success', $user->name.' added as '.$role->display_name.'.');
    }

    public function role(Request $request, int $user)
    {
        $tenant = $this->tenant();
        $role = $tenant->roles()->where('slug', (string) $request->input('role'))->firstOrFail();
        $member = $tenant->users()->where('users.id', $user)->firstOrFail();

        // Owner role is unique per business: only one owner at a time.
        if ($role->slug === 'owner') {
            $owners = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.tenant_id', $tenant->id)->where('roles.slug', 'owner')->pluck('user_id');

            if ($owners->isNotEmpty() && ! $owners->contains($user)) {
                throw ValidationException::withMessages(['role' => 'There can only be one owner.']);
            }
        }

        DB::table('user_roles')->where('tenant_id', $tenant->id)->where('user_id', $user)->delete();
        $member->assignTenantRole($tenant, $role);

        $this->audit->log('team.role.changed', $member, null, ['tenant_id' => $tenant->id, 'role' => $role->slug]);

        return back()->with('success', $member->name.' is now '.$role->display_name.'.');
    }

    public function destroy(Request $request, int $user)
    {
        $tenant = $this->tenant();
        $member = $tenant->users()->where('users.id', $user)->firstOrFail();

        $tenant->users()->detach($user);
        DB::table('user_roles')->where('tenant_id', $tenant->id)->where('user_id', $user)->delete();

        $this->audit->log('team.member.removed', $member, ['tenant_id' => $tenant->id], null);

        return back()->with('success', $member->name.' removed from the team.');
    }

    private function tenant()
    {
        $tenant = app(TenantContext::class)->tenant();
        $this->authorize('manageTeam', $tenant);

        return $tenant;
    }
}
