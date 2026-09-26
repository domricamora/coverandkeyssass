<?php

use App\Models\Module;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Maintenance\Services\MaintenanceService;
use App\Modules\PropertyManagement\Models\Room;
use App\Support\ModuleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 16 (Maintenance) — tickets with category / priority / assignment /
| status workflow / cost / notes / private attachments, room hand-back to
| housekeeping, permissions and isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    [$this->owner, $this->tenant, $this->property] = BookingFixtures::hotel(2);
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'workforce')->firstOrFail(), $this->tenant);
    $this->tech = MarketplaceFixtures::member($this->tenant, 'staff');
    MarketplaceFixtures::asTenant($this->tenant);
    $this->room = Room::query()->where('room_number', '101')->firstOrFail();
});

function mt(): MaintenanceService
{
    return app(MaintenanceService::class);
}

it('opens, assigns and walks a ticket through its workflow with a note trail', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->post(route('maintenance.store'), [
        'property_id' => $this->property->id, 'room_id' => $this->room->id, 'title' => 'Shower drain slow',
        'category' => 'plumbing', 'priority' => 'high',
    ])->assertRedirect();

    $ticket = MaintenanceTicket::query()->firstOrFail();
    expect($ticket->category)->toBe('plumbing')->and($ticket->status)->toBe(MaintenanceTicket::OPEN);

    $this->post(route('maintenance.assign', $ticket->reference), ['assigned_to' => $this->tech->id])->assertSessionHasNoErrors();
    expect($this->tech->notifications()->count())->toBe(1);

    PropertyManagementFixtures::login($this->tech, $this->tenant);
    foreach (['in_progress', 'on_hold', 'in_progress', 'resolved'] as $status) {
        $this->post(route('maintenance.transition', $ticket->reference), ['status' => $status, 'note' => 'step'])->assertSessionHasNoErrors();
    }
    $this->post(route('maintenance.notes.store', $ticket->reference), ['body' => 'Replaced the trap.'])->assertSessionHasNoErrors();

    // Closing, costing and assigning are for managers.
    $this->post(route('maintenance.transition', $ticket->reference), ['status' => 'closed'])->assertForbidden();
    $this->post(route('maintenance.cost', $ticket->reference), ['cost' => 450])->assertForbidden();
    $this->post(route('maintenance.assign', $ticket->reference), ['assigned_to' => null])->assertForbidden();

    PropertyManagementFixtures::login($this->owner, $this->tenant);
    $this->post(route('maintenance.cost', $ticket->reference), ['cost' => 450])->assertSessionHasNoErrors();
    $this->post(route('maintenance.transition', $ticket->reference), ['status' => 'closed'])->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(MaintenanceTicket::CLOSED)
        ->and((float) $ticket->cost)->toBe(450.0)
        ->and($ticket->started_at)->not->toBeNull()
        ->and($ticket->notes()->where('is_system', false)->count())->toBe(1)
        ->and($ticket->notes()->where('is_system', true)->count())->toBeGreaterThanOrEqual(7);

    $this->post(route('maintenance.notes.store', $ticket->reference), ['body' => 'late'])->assertSessionHasErrors('body');
    $this->post(route('maintenance.transition', $ticket->reference), ['status' => 'in_progress'])->assertSessionHasErrors('status');
    $this->get(route('maintenance.show', $ticket->reference))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Maintenance/Show')->where('ticket.cost', 450)->where('ticket.next', [])
        ->where('ticket.notes', fn ($notes) => collect($notes)->contains('body', 'Replaced the trap.')));
});

it('hands a repaired room back to housekeeping only when its last ticket is resolved', function () {
    $hk = app(HousekeepingService::class);
    $first = $hk->reportIssue($this->room, 'Aircon dead', null, 'urgent', true, $this->tech);
    $second = $hk->reportIssue($this->room, 'Lamp flickers', null, 'low', false, $this->tech);

    mt()->transition($first, MaintenanceTicket::RESOLVED, $this->owner);
    expect($this->room->refresh()->housekeeping_status)->not->toBe(Room::HK_DIRTY); // still has an open ticket

    mt()->transition($second, MaintenanceTicket::RESOLVED, $this->owner);
    expect($this->room->refresh()->housekeeping_status)->toBe(Room::HK_DIRTY)
        ->and(Room::query()->whereKey($this->room->id)->sellable()->exists())->toBeTrue();
});

it('keeps attachments private to the business', function () {
    Storage::fake('local');
    $ticket = mt()->open($this->property, $this->room, ['title' => 'Cracked tile'], $this->owner);
    PropertyManagementFixtures::login($this->tech, $this->tenant);

    $this->post(route('maintenance.attachments.store', $ticket->reference), ['file' => UploadedFile::fake()->image('tile.jpg')])->assertSessionHasNoErrors();
    $this->post(route('maintenance.attachments.store', $ticket->reference), ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');

    $file = $ticket->attachments()->firstOrFail();
    expect($file->disk)->toBe('local')->and($file->kind)->toBe('image');
    Storage::disk('local')->assertExists($file->path);

    $this->get(route('maintenance.attachments.show', [$ticket->reference, $file->id]))->assertOk();

    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'workforce')->firstOrFail(), $tenantB);
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('maintenance.attachments.show', [$ticket->reference, $file->id]))->assertNotFound();
    $this->get(route('maintenance.show', $ticket->reference))->assertNotFound();
    $this->get(route('maintenance.index'))->assertOk()->assertDontSee($ticket->reference);
});

it('guards assignment, ownership and room/property consistency', function () {
    $ticket = mt()->open($this->property, $this->room, ['title' => 'Door sticks'], $this->owner);
    $other = MarketplaceFixtures::member($this->tenant, 'staff');
    mt()->assign($ticket, $other, $this->owner);

    expect(fn () => mt()->transition($ticket, MaintenanceTicket::IN_PROGRESS, $this->tech))->toThrow(ValidationException::class) // someone else's
        ->and(fn () => mt()->transition($ticket, MaintenanceTicket::CLOSED, $this->owner))->toThrow(ValidationException::class) // must resolve first
        ->and(fn () => mt()->assign($ticket, \App\Models\User::factory()->create(), $this->owner))->toThrow(ValidationException::class);

    // A room of another property.
    $otherProperty = PropertyManagementFixtures::property($this->tenant, $this->owner, ['name' => 'Annex']);
    MarketplaceFixtures::asTenant($this->tenant);
    expect(fn () => mt()->open($otherProperty, $this->room, ['title' => 'x'], $this->owner))->toThrow(ValidationException::class);

    // Unassigned tickets are claimed by whoever starts them.
    $open = mt()->open($this->property, null, ['title' => 'Gate hinge'], $this->owner);
    mt()->transition($open, MaintenanceTicket::IN_PROGRESS, $this->tech);
    expect($open->refresh()->assigned_to)->toBe($this->tech->id);
});
