<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

function teamFixture(): array
{
    $owner = User::factory()->create();
    $tenant = Tenant::create(['name' => 'Hotel A', 'business_type' => 'hotel', 'status' => 'active']);

    // Same provisioning path as TenantController@store.
    RoleSeeder::ensureTenantRoles($tenant->id);

    $tenant->users()->attach($owner->id, ['status' => 'active', 'joined_at' => now()]);
    $owner->assignTenantRole($tenant, $tenant->roles()->where('slug', 'owner')->firstOrFail());

    return [$owner, $tenant];
}

function teamMember(Tenant $tenant, string $role = 'staff'): User
{
    $member = User::factory()->create();
    $tenant->users()->attach($member->id, ['status' => 'active', 'joined_at' => now()]);
    $member->assignTenantRole($tenant, $tenant->roles()->where('slug', $role)->firstOrFail());

    return $member;
}

function actAsOwnerOf(User $owner, Tenant $tenant): void
{
    test()->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    app(TenantContext::class)->set($tenant);
}

it('lists the team and adds a member with a role', function () {
    [$owner, $tenant] = teamFixture();
    actAsOwnerOf($owner, $tenant);

    $this->get(route('team'))->assertOk()->assertInertia(fn ($p) => $p->component('Team/Index')->has('members', 1)->where('members.0.role', 'owner'));

    $this->post(route('team.store'), ['name' => 'Pedro Cruz', 'email' => 'Pedro@example.com', 'role' => 'staff'])->assertSessionHasNoErrors();

    $member = User::query()->where('email', 'pedro@example.com')->firstOrFail();
    expect($member->belongsToTenant($tenant))->toBeTrue()
        ->and($member->tenantRole($tenant)?->slug)->toBe('staff');
});

it('rejects adding a member who is already on the team', function () {
    [$owner, $tenant] = teamFixture();
    actAsOwnerOf($owner, $tenant);

    $this->post(route('team.store'), ['name' => 'Pedro Cruz', 'email' => 'pedro@example.com', 'role' => 'staff'])->assertSessionHasNoErrors();
    $this->post(route('team.store'), ['name' => 'Pedro Again', 'email' => 'pedro@example.com', 'role' => 'staff'])->assertSessionHasErrors('email');
});

it('changes a member role and keeps a single owner', function () {
    [$owner, $tenant] = teamFixture();
    $member = teamMember($tenant);
    actAsOwnerOf($owner, $tenant);

    $this->patch(route('team.role', $member->id), ['role' => 'manager'])->assertSessionHasNoErrors();
    expect($member->fresh()->tenantRole($tenant)->slug)->toBe('manager');

    $this->patch(route('team.role', $member->id), ['role' => 'owner'])->assertSessionHasErrors('role');
    expect($member->fresh()->tenantRole($tenant)->slug)->toBe('manager');
});

it('removes a member and revokes their role', function () {
    [$owner, $tenant] = teamFixture();
    $member = teamMember($tenant);
    actAsOwnerOf($owner, $tenant);

    $this->delete(route('team.remove', $member->id))->assertRedirect();

    expect($member->fresh()->belongsToTenant($tenant))->toBeFalse()
        ->and($member->fresh()->tenantRole($tenant))->toBeNull();
});

it('prevents staff from managing the team', function () {
    [, $tenant] = teamFixture();
    $staff = teamMember($tenant);
    $other = teamMember($tenant);

    $this->actingAs($staff);
    session(['tenant_id' => $tenant->id]);

    $this->get(route('team'))->assertForbidden();
    $this->post(route('team.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'staff'])->assertForbidden();
    $this->delete(route('team.remove', $other->id))->assertForbidden();
});
