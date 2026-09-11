<?php

use App\Http\Livewire\TeamManager;
use App\Models\Role;
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
    \Database\Seeders\RoleSeeder::ensureTenantRoles($tenant->id);

    $tenant->users()->attach($owner->id, ['status' => 'active', 'joined_at' => now()]);
    $owner->assignTenantRole($tenant, $tenant->roles()->where('slug', 'owner')->firstOrFail());

    return [$owner, $tenant];
}

it('adds a team member and assigns a role', function () {
    [$owner, $tenant] = teamFixture();

    $this->actingAs($owner);
    app(TenantContext::class)->set($tenant);

    \Livewire\Livewire::test(TeamManager::class)
        ->set('name', 'Pedro Cruz')
        ->set('email', 'pedro@example.com')
        ->set('roleSlug', 'staff')
        ->call('addMember');

    $member = User::query()->where('email', 'pedro@example.com')->firstOrFail();

    expect($member->belongsToTenant($tenant))->toBeTrue()
        ->and($member->tenantRole($tenant)?->slug)->toBe('staff');
});

it('rejects adding a member who is already on the team', function () {
    [$owner, $tenant] = teamFixture();

    $this->actingAs($owner);
    app(TenantContext::class)->set($tenant);

    \Livewire\Livewire::test(TeamManager::class)
        ->set('name', 'Pedro Cruz')
        ->set('email', 'pedro@example.com')
        ->set('roleSlug', 'staff')
        ->call('addMember')
        ->set('name', 'Pedro Again')
        ->set('email', 'pedro@example.com')
        ->call('addMember')
        ->assertHasErrors(['email']);
});

it('changes a member role', function () {
    [$owner, $tenant] = teamFixture();

    $member = User::factory()->create();
    $tenant->users()->attach($member->id, ['status' => 'active', 'joined_at' => now()]);
    $member->assignTenantRole($tenant, $tenant->roles()->where('slug', 'staff')->firstOrFail());

    $this->actingAs($owner);
    app(TenantContext::class)->set($tenant);

    \Livewire\Livewire::test(TeamManager::class)
        ->call('changeRole', $member->id, 'manager');

    expect($member->fresh()->tenantRole($tenant)->slug)->toBe('manager');
});

it('removes a member and revokes their role', function () {
    [$owner, $tenant] = teamFixture();

    $member = User::factory()->create();
    $tenant->users()->attach($member->id, ['status' => 'active', 'joined_at' => now()]);
    $member->assignTenantRole($tenant, $tenant->roles()->where('slug', 'staff')->firstOrFail());

    $this->actingAs($owner);
    app(TenantContext::class)->set($tenant);

    \Livewire\Livewire::test(TeamManager::class)
        ->call('removeMember', $member->id);

    expect($member->fresh()->belongsToTenant($tenant))->toBeFalse()
        ->and($member->fresh()->tenantRole($tenant))->toBeNull();
});

it('prevents staff from using the team manager', function () {
    [$owner, $tenant] = teamFixture();

    $staff = User::factory()->create();
    $tenant->users()->attach($staff->id, ['status' => 'active', 'joined_at' => now()]);
    $staff->assignTenantRole($tenant, $tenant->roles()->where('slug', 'staff')->firstOrFail());

    $this->actingAs($staff);
    session(['tenant_id' => $tenant->id]);

    // Full-page Livewire component must refuse non-managers.
    $this->get(route('team'))->assertForbidden();
});
