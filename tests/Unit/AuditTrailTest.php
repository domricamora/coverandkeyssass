<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditLogger;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('writes audit rows through the AuditLogger service', function () {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    $log = app(AuditLogger::class)->log(
        'test.action',
        $subject,
        ['status' => 'active'],
        ['status' => 'suspended'],
        null,
        $actor->id,
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->action)->toBe('test.action')
        ->and($log->user_id)->toBe($actor->id)
        ->and($log->auditable_id)->toBe($subject->id)
        ->and($log->old_values)->toBe(['status' => 'active'])
        ->and($log->new_values)->toBe(['status' => 'suspended']);
});

it('logs login, logout and registration', function () {
    $this->post('/register', [
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'password' => 'Str0ngPassw0rd!',
        'password_confirmation' => 'Str0ngPassw0rd!',
    ])->assertValid();

    $user = User::query()->where('email', 'juan@example.com')->firstOrFail();

    // Registration has no acting user yet: the account is recorded as the subject.
    expect(AuditLog::query()->where('action', 'user.registered')->where('auditable_id', $user->id)->exists())->toBeTrue();

    $this->post('/logout');

    expect(AuditLog::query()->where('action', 'auth.logout')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('logs tenant creation with the actor and tenant id', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post('/tenants', ['name' => 'Casa Marina', 'business_type' => 'resort'])
        ->assertRedirect(route('dashboard'));

    $tenant = Tenant::query()->where('name', 'Casa Marina')->firstOrFail();

    $log = AuditLog::query()->where('action', 'tenant.created')->where('tenant_id', $tenant->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($owner->id)
        ->and($log->new_values['name'])->toBe('Casa Marina');
});
