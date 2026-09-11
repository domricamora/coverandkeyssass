<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Support\ModuleService;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ModuleSeeder::class);

    $this->service = app(ModuleService::class);
});

it('allows a super admin to list modules', function () {
    $admin = User::factory()->create();
    $admin->assignPlatformRole();

    $this->actingAs($admin)
        ->get(route('admin.modules.index'))
        ->assertOk()
        ->assertSee('Modules');
});

it('allows a super admin to create a module', function () {
    $admin = User::factory()->create();
    $admin->assignPlatformRole();

    $this->actingAs($admin)
        ->post(route('admin.modules.store'), [
            'name' => 'Test Module',
            'slug' => 'test-module',
            'category' => 'operations',
            'trial_days' => 7,
        ])
        ->assertRedirect(route('admin.modules.index'));

    expect(Module::query()->where('slug', 'test-module')->exists())->toBeTrue();
});

it('allows a super admin to edit a module', function () {
    $admin = User::factory()->create();
    $admin->assignPlatformRole();
    $module = Module::query()->where('slug', 'property')->first();

    $this->actingAs($admin)
        ->patch(route('admin.modules.update', $module), [
            'name' => 'Updated Property',
            'category' => 'operations',
            'status' => 'active',
            'trial_days' => 30,
        ])
        ->assertRedirect(route('admin.modules.index'));

    expect($module->fresh()->name)->toBe('Updated Property');
});

it('prevents deleting a core module', function () {
    $admin = User::factory()->create();
    $admin->assignPlatformRole();
    $core = Module::query()->where('slug', 'core')->first();

    $this->actingAs($admin)
        ->delete(route('admin.modules.destroy', $core))
        ->assertRedirect();

    expect(Module::query()->where('slug', 'core')->exists())->toBeTrue();
});

it('allows a super admin to enable a module for a tenant', function () {
    $admin = User::factory()->create();
    $admin->assignPlatformRole();
    $tenant = Tenant::factory()->create();
    $module = Module::query()->where('slug', 'property')->first();

    $response = $this->actingAs($admin)
        ->post(route('admin.tenants.modules.store', $tenant), [
            'module_id' => $module->id,
        ]);

    $response->assertRedirect();

    expect(TenantModule::query()
        ->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('module_id', $module->id)
        ->where('status', 'active')
        ->exists())->toBeTrue();
});

it('allows a super admin to disable a module for a tenant', function () {
    $admin = User::factory()->create();
    $admin->assignPlatformRole();
    $tenant = Tenant::factory()->create();
    $module = Module::query()->where('slug', 'property')->first();

    $this->service->enableForTenant($module, $tenant);

    $this->actingAs($admin)
        ->delete(route('admin.tenants.modules.destroy', [$tenant, $module]))
        ->assertRedirect();

    $tm = TenantModule::query()
        ->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('module_id', $module->id)
        ->first();

    expect($tm->status)->toBe('disabled');
});

it('blocks non-admins from module management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.modules.index'))
        ->assertForbidden();
});