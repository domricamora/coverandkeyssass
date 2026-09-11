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

it('assigns the owner role to the tenant creator', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/tenants', ['name' => 'Harbor Hotel', 'business_type' => 'hotel'])
        ->assertRedirect(route('dashboard'));

    $tenant = Tenant::query()->where('name', 'Harbor Hotel')->firstOrFail();

    expect($user->belongsToTenant($tenant))->toBeTrue()
        ->and($user->tenantRole($tenant)?->slug)->toBe('owner')
        ->and($tenant->roles()->where('slug', 'owner')->exists())->toBeTrue()
        ->and($tenant->roles()->count())->toBe(4);
});

it('seeds the platform super_admin role only once', function () {
    expect(Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->count())->toBe(1)
        ->and(Role::query()->whereNull('tenant_id')->count())->toBe(1);
});

it('blocks suspended users from logging in', function () {
    $user = User::factory()->create([
        'status' => 'suspended',
        'password' => 'Str0ngPassw0rd!',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'Str0ngPassw0rd!',
    ])->assertInvalid('email');

    expect(auth()->check())->toBeFalse();
});

it('records last login timestamps', function () {
    $user = User::factory()->create([
        'password' => 'Str0ngPassw0rd!',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'Str0ngPassw0rd!',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
