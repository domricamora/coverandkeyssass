<?php

namespace App\Http\Livewire;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class TeamManager extends Component
{
    use AuthorizesRequests;

    public Tenant $tenant;

    public string $name = '';

    public string $email = '';

    public string $roleSlug = 'staff';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'roleSlug' => ['required', Rule::in($this->tenant->roles()->pluck('slug')->all())],
        ];
    }

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->tenant();

        if (! $tenant) {
            $this->redirect(route('tenants.index'));

            return;
        }

        $this->authorize('manageTeam', $tenant);

        $this->tenant = $tenant;
    }

    public function addMember(AuditLogger $audit): void
    {
        $this->authorize('manageTeam', $this->tenant);

        $this->validate();

        $email = \strtolower($this->email);

        if ($this->tenant->users()->where('users.email', $email)->exists()) {
            $this->addError('email', 'This person is already on the team.');

            return;
        }

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user && $user->trashed()) {
            $user->restore();
        }

        if (! $user) {
            // New platform account. A one-time password is generated and the
            // user is instructed to reset it via the password reset flow.
            $user = User::create([
                'name' => $this->name,
                'email' => $email,
                'password' => Hash::make(Str::password(24)),
                'status' => 'active',
            ]);
        }

        $this->tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $role = $this->tenant->roles()->where('slug', $this->roleSlug)->firstOrFail();
        $user->assignTenantRole($this->tenant, $role);

        $audit->log('team.member.added', $user, null, [
            'tenant_id' => $this->tenant->id,
            'role' => $role->slug,
        ]);

        $this->reset(['name', 'email']);
        $this->roleSlug = 'staff';

        $this->dispatch('member-added');
    }

    public function changeRole(int $userId, string $roleSlug, AuditLogger $audit): void
    {
        $this->authorize('manageTeam', $this->tenant);

        $role = $this->tenant->roles()->where('slug', $roleSlug)->firstOrFail();
        $member = $this->tenant->users()->where('users.id', $userId)->firstOrFail();

        // Owner role is unique per tenant: only one owner at a time.
        if ($role->slug === 'owner') {
            $ownerIds = \Illuminate\Support\Facades\DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.tenant_id', $this->tenant->id)
                ->where('roles.slug', 'owner')
                ->pluck('user_id');

            if ($ownerIds->isNotEmpty() && ! $ownerIds->contains($userId)) {
                $this->addError('roleSlug', 'There can only be one owner.');

                return;
            }
        }

        \Illuminate\Support\Facades\DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.tenant_id', $this->tenant->id)
            ->where('user_id', $userId)
            ->delete();

        $member->assignTenantRole($this->tenant, $role);

        $audit->log('team.role.changed', $member, null, [
            'tenant_id' => $this->tenant->id,
            'role' => $role->slug,
        ]);

        $this->dispatch('role-changed');
    }

    public function removeMember(int $userId, AuditLogger $audit): void
    {
        $this->authorize('manageTeam', $this->tenant);

        $member = $this->tenant->users()->where('users.id', $userId)->firstOrFail();

        $this->tenant->users()->detach($userId);

        \Illuminate\Support\Facades\DB::table('user_roles')
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $userId)
            ->delete();

        $audit->log('team.member.removed', $member, ['tenant_id' => $this->tenant->id], null);

        $this->dispatch('member-removed');
    }

    public function render()
    {
        $members = $this->tenant->tenantUsers()
            ->with('user:id,name,email,status')
            ->orderBy('created_at')
            ->get()
            ->map(function ($tu) {
                $tu->role = auth()->user() && app(TenantContext::class)->id()
                    ? \App\Models\Role::query()
                        ->join('user_roles', 'user_roles.role_id', '=', 'roles.id')
                        ->where('user_roles.user_id', $tu->user_id)
                        ->where('user_roles.tenant_id', $this->tenant->id)
                        ->select('roles.slug', 'roles.display_name')
                        ->first()
                    : null;

                return $tu;
            });

        return view('livewire.team-manager', [
            'members' => $members,
            'roles' => $this->tenant->roles()->orderBy('id')->get(['slug', 'display_name']),
            'isOwnerOnly' => true,
        ]);
    }
}
