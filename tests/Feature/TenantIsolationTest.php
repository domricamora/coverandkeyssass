<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

function provisionTenant(string $name): array
{
    $owner = User::factory()->create();
    $tenant = Tenant::create(['name' => $name, 'business_type' => 'resort', 'status' => 'active']);

    // Same provisioning path as TenantController@store.
    \Database\Seeders\RoleSeeder::ensureTenantRoles($tenant->id);

    $tenant->users()->attach($owner->id, ['status' => 'active', 'joined_at' => now()]);
    $owner->assignTenantRole($tenant, $tenant->roles()->where('slug', 'owner')->firstOrFail());

    return [$owner, $tenant];
}

it('denies users access to tenants they do not belong to', function () {
    [$ownerA, $tenantA] = provisionTenant('Hotel A');
    [$ownerB, $tenantB] = provisionTenant('Hotel B');

    $this->actingAs($ownerB);

    // Owner B must not be able to switch into Hotel A's context.
    $this->post(route('tenants.switch', $tenantA))->assertForbidden();
});

it('rejects tenant context without membership', function () {
    [$ownerA, $tenantA] = provisionTenant('Hotel A');
    [, $tenantB] = provisionTenant('Hotel B');

    // Owner A only belongs to Hotel A; forging B's id in the session must bounce.
    $this->actingAs($ownerA);
    session(['tenant_id' => $tenantB->id]);

    $this->get(route('dashboard'))
        ->assertRedirect(route('tenants.index'));
});

it('redirects to business selection when no tenant is chosen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('tenants.index'));
});

it('scopes tenant dashboard access to members', function () {
    [$owner, $tenant] = provisionTenant('Hotel A');

    $this->actingAs($owner);
    session(['tenant_id' => $tenant->id]);

    $this->get(route('dashboard'))->assertOk();
});

it('prevents staff without permission from managing the team', function () {
    [$owner, $tenant] = provisionTenant('Hotel A');

    $staff = User::factory()->create();
    $tenant->users()->attach($staff->id, ['status' => 'active', 'joined_at' => now()]);
    $staff->assignTenantRole($tenant, $tenant->roles()->where('slug', 'staff')->firstOrFail());

    $this->actingAs($staff);
    session(['tenant_id' => $tenant->id]);

    $this->get(route('team'))->assertForbidden();
});

it('allows owners to open the team page', function () {
    [$owner, $tenant] = provisionTenant('Hotel A');

    $this->actingAs($owner);
    session(['tenant_id' => $tenant->id]);

    $this->get(route('team'))->assertOk();
});

