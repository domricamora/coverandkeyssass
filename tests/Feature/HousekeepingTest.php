<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\PropertyManagement\Models\Room;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 15 (Housekeeping) — room status, check-out → dirty + task, start /
| complete / inspect (pass and fail), assignment + notification, issue
| reports taking rooms out of order (unsellable), board, permissions.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-09-05 14:00'));

    [$this->owner, $this->tenant, $this->property, $this->type] = BookingFixtures::hotel(2);
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'workforce')->firstOrFail(), $this->tenant);
    $this->housekeeper = MarketplaceFixtures::member($this->tenant, 'staff');
    MarketplaceFixtures::asTenant($this->tenant);
    $this->room = Room::query()->where('room_number', '101')->firstOrFail();
});

function hk(): HousekeepingService
{
    return app(HousekeepingService::class);
}

it('turns a checked-out room dirty and queues a checkout clean', function () {
    $stay = BookingFixtures::reserve($this->property, $this->type, ['check_in' => '2030-09-05', 'check_out' => '2030-09-06']);
    $bookings = app(BookingService::class);
    $bookings->transition($stay, Booking::CHECKED_IN);

    $this->travelTo(CarbonImmutable::parse('2030-09-06 11:00'));
    $bookings->transition($stay->refresh(), Booking::CHECKED_OUT);

    $roomId = $stay->rooms()->value('room_id');
    $task = HousekeepingTask::query()->where('room_id', $roomId)->firstOrFail();

    expect(Room::find($roomId)->housekeeping_status)->toBe(Room::HK_DIRTY)
        ->and($task->type)->toBe('checkout_clean')
        ->and($task->due_on->toDateString())->toBe('2030-09-06')
        ->and($task->booking_id)->toBe($stay->id);
});

it('runs clean → inspect, and a failed inspection queues a high-priority re-clean', function () {
    hk()->setRoomStatus($this->room, Room::HK_DIRTY, $this->owner);
    $task = hk()->createTask($this->room, 'checkout_clean', '2030-09-05', $this->owner, $this->housekeeper);

    expect($this->housekeeper->notifications()->count())->toBe(1);

    hk()->start($task, $this->housekeeper);
    expect($this->room->refresh()->housekeeping_status)->toBe(Room::HK_CLEANING);

    hk()->complete($task, $this->housekeeper);
    expect($this->room->refresh()->housekeeping_status)->toBe(Room::HK_CLEAN);

    $redo = hk()->inspect($task->refresh(), $this->owner, false, 'Hair in the shower');
    expect($this->room->refresh()->housekeeping_status)->toBe(Room::HK_DIRTY)
        ->and($redo->priority)->toBe('high')
        ->and($redo->assigned_to)->toBe($this->housekeeper->id)
        ->and($redo->notes)->toContain('Hair in the shower')
        ->and(fn () => hk()->inspect($task->refresh(), $this->owner, true))->toThrow(ValidationException::class); // already inspected

    hk()->start($redo, $this->housekeeper);
    hk()->complete($redo, $this->housekeeper);
    expect(hk()->inspect($redo->refresh(), $this->owner, true))->toBeNull()
        ->and($this->room->refresh()->housekeeping_status)->toBe(Room::HK_INSPECTED);
});

it('lets housekeepers work only their own or unassigned tasks', function () {
    $colleague = MarketplaceFixtures::member($this->tenant, 'staff');
    $theirs = hk()->createTask($this->room, 'stayover', '2030-09-05', $this->owner, $colleague);
    $open = hk()->createTask($this->room, 'turndown', '2030-09-05', $this->owner);

    expect(fn () => hk()->start($theirs, $this->housekeeper))->toThrow(ValidationException::class)
        ->and(fn () => hk()->complete($open, $this->housekeeper))->toThrow(ValidationException::class); // not started

    hk()->start($open, $this->housekeeper);
    expect($open->refresh()->assigned_to)->toBe($this->housekeeper->id); // claimed

    hk()->start($theirs, $this->owner); // managers may
    expect($theirs->refresh()->status)->toBe(HousekeepingTask::IN_PROGRESS);

    // Tasks go to members of this business only.
    expect(fn () => hk()->createTask($this->room, 'stayover', '2030-09-05', $this->owner, User::factory()->create()))->toThrow(ValidationException::class);
});

it('takes a room out of sellable inventory when an issue puts it out of order', function () {
    $ticket = hk()->reportIssue($this->room, 'Aircon leaking', 'Water on the floor', 'high', true, $this->housekeeper);

    expect($ticket->status)->toBe(MaintenanceTicket::OPEN)
        ->and($ticket->room_out_of_order)->toBeTrue()
        ->and($this->room->refresh()->housekeeping_status)->toBe(Room::HK_OUT_OF_ORDER)
        ->and(Room::query()->where('room_type_id', $this->type->id)->sellable()->pluck('room_number')->all())->toBe(['102']);

    // Two rooms, one out of order → only one can be sold.
    BookingFixtures::reserve($this->property, $this->type);
    expect(fn () => BookingFixtures::reserve($this->property, $this->type))->toThrow(ValidationException::class);

    // A maintenance (not out-of-order) report keeps the room sellable.
    $room102 = Room::query()->where('room_number', '102')->firstOrFail();
    hk()->reportIssue($room102, 'Loose towel rail', null, 'low', false, $this->housekeeper);
    expect($room102->refresh()->housekeeping_status)->toBe(Room::HK_MAINTENANCE)
        ->and(Room::query()->whereKey($room102->id)->sellable()->exists())->toBeTrue();
});

it('runs the board with module gating, permissions and isolation', function () {
    $task = hk()->createTask($this->room, 'checkout_clean', '2030-09-05', $this->owner, $this->housekeeper);

    PropertyManagementFixtures::login($this->housekeeper, $this->tenant);
    $this->get(route('housekeeping.index', ['mine' => 1]))->assertOk()->assertSee('Room 101')->assertSee('Checkout Clean');
    $this->post(route('housekeeping.tasks.start', $task->id))->assertSessionHasNoErrors();
    $this->post(route('housekeeping.tasks.complete', $task->id))->assertSessionHasNoErrors();
    $this->post(route('housekeeping.rooms.status', $this->room->id), ['housekeeping_status' => 'clean'])->assertForbidden();
    $this->post(route('housekeeping.tasks.inspect', $task->id), ['passed' => 1])->assertForbidden();
    $this->post(route('housekeeping.rooms.issue', $this->room->id), ['title' => 'Broken lamp'])->assertSessionHasNoErrors();
    expect(MaintenanceTicket::query()->count())->toBe(1);

    PropertyManagementFixtures::login($this->owner, $this->tenant);
    $this->post(route('housekeeping.tasks.inspect', $task->id), ['passed' => 1])->assertSessionHasNoErrors();
    $this->post(route('housekeeping.rooms.status', $this->room->id), ['housekeeping_status' => 'out_of_order'])->assertSessionHasNoErrors();
    expect($this->room->refresh()->housekeeping_status)->toBe(Room::HK_OUT_OF_ORDER);

    // Without the workforce module the board is closed.
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('housekeeping.index'))->assertForbidden();

    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'workforce')->firstOrFail(), $tenantB);
    $this->get(route('housekeeping.index'))->assertOk()->assertDontSee('Room 101');
    $this->post(route('housekeeping.rooms.status', $this->room->id), ['housekeeping_status' => 'dirty'])->assertNotFound();
    $this->post(route('housekeeping.tasks.cancel', $task->id))->assertNotFound();
});
