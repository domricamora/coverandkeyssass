<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Support\ModuleService;
use Carbon\Carbon;
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

it('seeds the canonical module list', function () {
    expect(Module::query()->count())->toBe(10)
        ->and(Module::query()->where('slug', 'core')->exists())->toBeTrue()
        ->and(Module::query()->where('slug', 'booking')->exists())->toBeTrue();
});

it('marks core modules as non-deletable', function () {
    $core = Module::query()->where('slug', 'core')->first();

    expect($core->is_core)->toBeTrue();
});

it('enables a module for a tenant', function () {
    $tenant = Tenant::factory()->create();
    $module = Module::query()->where('slug', 'property')->first();

    $tenantModule = $this->service->enableForTenant($module, $tenant);

    expect($tenantModule->status)->toBe('active')
        ->and($tenantModule->tenant_id)->toBe($tenant->id)
        ->and($tenantModule->module_id)->toBe($module->id);
});

it('auto-enables dependencies when enabling a module', function () {
    $tenant = Tenant::factory()->create();
    $booking = Module::query()->where('slug', 'booking')->first();

    $this->service->enableForTenant($booking, $tenant);

    $activeIds = TenantModule::query()
        ->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('status', 'active')
        ->pluck('module_id')
        ->all();

    $property = Module::query()->where('slug', 'property')->first();

    // booking depends on core + property; core is platform-level (not a tenant module).
    expect($activeIds)->toContain($booking->id)
        ->toContain($property->id);
});

it('sets a trial period when the module has trial days', function () {
    $tenant = Tenant::factory()->create();
    $module = Module::query()->where('slug', 'property')->first();

    $tenantModule = $this->service->enableForTenant($module, $tenant);

    expect($tenantModule->trial_ends_at)->not->toBeNull()
        ->and($tenantModule->trial_ends_at)->toBeInstanceOf(Carbon::class)
        ->and($tenantModule->trial_ends_at->timestamp)->toBeGreaterThan(now()->timestamp)
        ->and((int) ceil($tenantModule->trial_ends_at->diffInDays(now(), true)))->toBe(14);
});

it('disables a module for a tenant', function () {
    $tenant = Tenant::factory()->create();
    $module = Module::query()->where('slug', 'property')->first();

    $this->service->enableForTenant($module, $tenant);
    $this->service->disableForTenant($module, $tenant);

    $tm = TenantModule::query()
        ->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('module_id', $module->id)
        ->first();

    expect($tm->status)->toBe('disabled');
});

it('prevents disabling a module that is required by an active module', function () {
    $tenant = Tenant::factory()->create();
    $booking = Module::query()->where('slug', 'booking')->first();
    $property = Module::query()->where('slug', 'property')->first();

    $this->service->enableForTenant($booking, $tenant);

    $this->expectException(\RuntimeException::class);
    $this->service->disableForTenant($property, $tenant);
});

it('checks whether a module is enabled for a tenant', function () {
    $tenant = Tenant::factory()->create();
    $module = Module::query()->where('slug', 'property')->first();

    expect($this->service->isEnabled($module, $tenant))->toBeFalse();

    $this->service->enableForTenant($module, $tenant);

    expect($this->service->isEnabled($module, $tenant))->toBeTrue();
});

it('returns all active modules for a tenant', function () {
    $tenant = Tenant::factory()->create();
    $property = Module::query()->where('slug', 'property')->first();
    $workforce = Module::query()->where('slug', 'workforce')->first();

    $this->service->enableForTenant($property, $tenant);
    $this->service->enableForTenant($workforce, $tenant);

    $active = $this->service->activeForTenant($tenant);

    expect($active->count())->toBeGreaterThanOrEqual(2);
});