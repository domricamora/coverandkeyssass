<?php

use App\Models\AuditLog;
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

function superAdmin(): User
{
    $user = User::factory()->create();
    $role = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail();
    $user->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => null]]);

    return $user;
}

it('grants the admin area only to platform admins', function () {
    $regular = User::factory()->create();

    $this->actingAs($regular)->get(route('admin.dashboard'))->assertForbidden();

    $this->actingAs(superAdmin())->get(route('admin.dashboard'))->assertOk();
});

it('suspends and activates users from the admin area', function () {
    $admin = superAdmin();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $user))
        ->assertRedirect();

    expect($user->fresh()->status)->toBe('suspended');

    $this->post(route('admin.users.activate', $user))->assertRedirect();

    expect($user->fresh()->status)->toBe('active');

    expect(AuditLog::query()->where('action', 'platform.user.suspended')->where('user_id', $admin->id)->exists())->toBeTrue();
});

it('protects other platform admins from suspension', function () {
    $admin = superAdmin();
    $other = superAdmin();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $other))
        ->assertRedirect();

    expect($other->fresh()->status)->toBe('active');
});

it('suspends tenants so members lose context access', function () {
    $admin = superAdmin();

    $owner = User::factory()->create();
    $tenant = Tenant::create(['name' => 'Suspend Me', 'business_type' => 'hotel', 'status' => 'active']);
    \Database\Seeders\RoleSeeder::ensureTenantRoles($tenant->id);
    $tenant->users()->attach($owner->id, ['status' => 'active', 'joined_at' => now()]);
    $owner->assignTenantRole($tenant, $tenant->roles()->where('slug', 'owner')->firstOrFail());

    $this->actingAs($admin)
        ->post(route('admin.tenants.suspend', $tenant))
        ->assertRedirect();

    expect($tenant->fresh()->status)->toBe('suspended');

    // The owner can no longer enter the suspended tenant's context.
    $this->actingAs($owner);

    $this->post(route('tenants.switch', $tenant))->assertForbidden();
});

it('creates a super admin through the artisan command', function () {
    $this->artisan('superadmin:create', [
        'name' => 'Ops Admin',
        'email' => 'ops@example.test',
        '--password' => 'SuperSecure2026!',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'ops@example.test')->firstOrFail();

    expect($user->isPlatformAdmin())->toBeTrue()
        ->and($user->status)->toBe('active')
        ->and(AuditLog::query()->where('action', 'platform.superadmin.created')->exists())->toBeTrue();
});

it('rejects weak passwords for super admin provisioning', function () {
    $this->artisan('superadmin:create', [
        'name' => 'Ops Admin',
        'email' => 'weak@example.test',
        '--password' => 'short',
    ])->assertFailed();

    expect(User::query()->where('email', 'weak@example.test')->exists())->toBeFalse();
});
