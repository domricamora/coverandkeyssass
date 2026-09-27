<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\PlatformAdmin\Models\Setting;
use App\Support\Currency;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('shows a symbol, never an ISO code: business symbol, else platform default, else ₱', function () {
    expect(Currency::format(1250))->toBe('₱1,250.00')
        ->and(Currency::format(-5, null, 0))->toBe('-₱5');

    Setting::put(['currency_symbol' => '$']);
    expect(Currency::format(1250))->toBe('$1,250.00');

    $tenant = Tenant::create(['name' => 'Hotel A', 'business_type' => 'hotel', 'status' => 'active', 'settings' => ['currency_symbol' => '€']]);
    expect(Currency::format(1250, $tenant))->toBe('€1,250.00');

    app(TenantContext::class)->set($tenant); // the active business is used when none is passed
    expect(Currency::symbol())->toBe('€');
});

it('lets the owner set the business symbol in Business settings and shares it with the React pages', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::create(['name' => 'Hotel A', 'business_type' => 'hotel', 'status' => 'active']);
    RoleSeeder::ensureTenantRoles($tenant->id);
    $tenant->users()->attach($owner->id, ['status' => 'active', 'joined_at' => now()]);
    $owner->assignTenantRole($tenant, $tenant->roles()->where('slug', 'owner')->firstOrFail());
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    app(TenantContext::class)->set($tenant);

    $this->patch(route('tenants.update'), ['name' => 'Hotel A', 'currency_symbol' => 'US$'])->assertRedirect();
    expect($tenant->fresh()->settings['currency_symbol'])->toBe('US$');

    $this->get(route('tenants.settings'))->assertOk()
        ->assertInertia(fn ($p) => $p->where('company.currency_symbol', 'US$')->where('moneySymbol', 'US$'));

    $this->patch(route('tenants.update'), ['name' => 'Hotel A', 'currency_symbol' => ''])->assertRedirect();
    expect(Currency::symbol($tenant->fresh()))->toBe('₱');
});
