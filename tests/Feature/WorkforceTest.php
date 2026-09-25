<?php

use App\Models\Module;
use App\Models\User;
use App\Modules\Housekeeping\Services\HousekeepingService;
use App\Modules\PropertyManagement\Models\Room;
use App\Modules\Workforce\Models\Attendance;
use App\Modules\Workforce\Models\Department;
use App\Modules\Workforce\Models\Employee;
use App\Modules\Workforce\Models\LeaveRequest;
use App\Modules\Workforce\Models\Position;
use App\Modules\Workforce\Models\Shift;
use App\Modules\Workforce\Services\WorkforceService;
use App\Support\ModuleService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\PropertyManagementFixtures;

/*
| Phase 17 (Staff Management) — employees, departments, positions, roster
| (overlap / length / leave rules), attendance (late minutes), leave
| (approval cancels shifts), "My work", permissions and isolation.
*/

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    BookingFixtures::bootstrap();
    $this->travelTo(CarbonImmutable::parse('2030-06-03 06:00')); // Monday

    [$this->owner, $this->tenant, $this->property] = BookingFixtures::hotel();
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'workforce')->firstOrFail(), $this->tenant);
    $this->account = MarketplaceFixtures::member($this->tenant, 'staff');
    MarketplaceFixtures::asTenant($this->tenant);

    $this->dept = Department::create(['name' => 'Housekeeping']);
    $this->position = Position::create(['name' => 'Room Attendant', 'department_id' => $this->dept->id, 'hourly_rate' => 95]);
    $this->maria = app(WorkforceService::class)->hire([
        'name' => 'Maria Santos', 'employment_type' => 'full_time', 'department_id' => $this->dept->id,
        'position_id' => $this->position->id, 'user_id' => $this->account->id,
    ], $this->owner);
});

function wf(): WorkforceService
{
    return app(WorkforceService::class);
}

it('hires employees with numbers and only links free member accounts', function () {
    $juan = wf()->hire(['name' => 'Juan Cruz', 'employment_type' => 'part_time'], $this->owner);

    expect($this->maria->employee_no)->toBe('EMP-0001')
        ->and($juan->employee_no)->toBe('EMP-0002')
        ->and(fn () => wf()->hire(['name' => 'Twin', 'employment_type' => 'contract', 'user_id' => $this->account->id], $this->owner))->toThrow(ValidationException::class)
        ->and(fn () => wf()->hire(['name' => 'Outsider', 'employment_type' => 'contract', 'user_id' => User::factory()->create()->id], $this->owner))->toThrow(ValidationException::class);
});

it('builds a roster without overlaps, overlong shifts or inactive staff', function () {
    wf()->scheduleShift($this->maria, '2030-06-03 07:00', '2030-06-03 15:00', $this->owner);

    expect(fn () => wf()->scheduleShift($this->maria, '2030-06-03 14:00', '2030-06-03 18:00', $this->owner))->toThrow(ValidationException::class)
        ->and(fn () => wf()->scheduleShift($this->maria, '2030-06-04 06:00', '2030-06-04 23:00', $this->owner))->toThrow(ValidationException::class)
        ->and(fn () => wf()->scheduleShift($this->maria, '2030-06-04 10:00', '2030-06-04 10:05', $this->owner))->toThrow(ValidationException::class);

    // Back-to-back is fine; a cancelled shift frees the slot.
    $evening = wf()->scheduleShift($this->maria, '2030-06-03 15:00', '2030-06-03 19:00', $this->owner);
    wf()->cancelShift($evening);
    wf()->scheduleShift($this->maria, '2030-06-03 16:00', '2030-06-03 20:00', $this->owner);

    wf()->update($this->maria, ['status' => Employee::TERMINATED]);
    expect(fn () => wf()->scheduleShift($this->maria->refresh(), '2030-06-05 07:00', '2030-06-05 15:00', $this->owner))->toThrow(ValidationException::class);
});

it('clocks in against the shift, counts lateness and refuses double clock-ins', function () {
    $shift = wf()->scheduleShift($this->maria, '2030-06-03 07:00', '2030-06-03 15:00', $this->owner);

    $this->travelTo(CarbonImmutable::parse('2030-06-03 07:12'));
    $in = wf()->clockIn($this->maria);
    expect($in->shift_id)->toBe($shift->id)->and($in->late_minutes)->toBe(12)
        ->and(fn () => wf()->clockIn($this->maria))->toThrow(ValidationException::class);

    $this->travelTo(CarbonImmutable::parse('2030-06-03 15:02'));
    expect(wf()->clockOut($this->maria)->minutes_worked)->toBe(470)
        ->and(fn () => wf()->clockOut($this->maria))->toThrow(ValidationException::class);

    // Early for the next shift (within 2 h) → not late; nothing scheduled → unscheduled.
    wf()->scheduleShift($this->maria, '2030-06-04 07:00', '2030-06-04 15:00', $this->owner);
    $this->travelTo(CarbonImmutable::parse('2030-06-04 06:30'));
    expect(wf()->clockIn($this->maria)->late_minutes)->toBe(0);
    wf()->clockOut($this->maria);

    $this->travelTo(CarbonImmutable::parse('2030-06-06 10:00'));
    expect(wf()->clockIn($this->maria)->shift_id)->toBeNull();
});

it('approves leave by cancelling shifts in the range and blocks new ones', function () {
    $mon = wf()->scheduleShift($this->maria, '2030-06-10 07:00', '2030-06-10 15:00', $this->owner);
    $tue = wf()->scheduleShift($this->maria, '2030-06-11 07:00', '2030-06-11 15:00', $this->owner);
    $fri = wf()->scheduleShift($this->maria, '2030-06-14 07:00', '2030-06-14 15:00', $this->owner);

    $leave = wf()->requestLeave($this->maria, 'vacation', '2030-06-10', '2030-06-12', 'Family trip');
    expect(fn () => wf()->requestLeave($this->maria, 'sick', '2030-06-12', '2030-06-13', null))->toThrow(ValidationException::class)
        ->and(fn () => wf()->requestLeave($this->maria, 'sick', '2030-06-12', '2030-06-11', null))->toThrow(ValidationException::class);

    expect(wf()->decideLeave($leave, true, $this->owner))->toBe(2)
        ->and($mon->refresh()->status)->toBe(Shift::CANCELLED)
        ->and($tue->refresh()->status)->toBe(Shift::CANCELLED)
        ->and($fri->refresh()->status)->toBe(Shift::SCHEDULED)
        ->and(fn () => wf()->scheduleShift($this->maria, '2030-06-12 07:00', '2030-06-12 15:00', $this->owner))->toThrow(ValidationException::class)
        ->and(fn () => wf()->decideLeave($leave->refresh(), false, $this->owner))->toThrow(ValidationException::class);

    $rejected = wf()->requestLeave($this->maria, 'unpaid', '2030-06-20', '2030-06-20', null);
    wf()->decideLeave($rejected, false, $this->owner, 'Peak weekend');
    $withdrawn = wf()->requestLeave($this->maria, 'sick', '2030-06-25', '2030-06-25', null);
    wf()->cancelLeave($withdrawn);

    expect($rejected->refresh()->status)->toBe(LeaveRequest::REJECTED)->and($withdrawn->refresh()->status)->toBe(LeaveRequest::CANCELLED);
});

it('gives staff a "My work" page with shifts, tasks, clock and leave', function () {
    wf()->scheduleShift($this->maria, '2030-06-03 07:00', '2030-06-03 15:00', $this->owner);
    $room = Room::query()->firstOrFail();
    app(HousekeepingService::class)->createTask($room, 'checkout_clean', '2030-06-03', $this->owner, $this->account);

    PropertyManagementFixtures::login($this->account, $this->tenant);
    $this->get(route('my-work.index'))->assertOk()->assertSee('7:00 AM–3:00 PM')->assertSee('Checkout Clean')->assertSee('Clock in');

    $this->travelTo(CarbonImmutable::parse('2030-06-03 07:05'));
    $this->post(route('my-work.clock'), ['action' => 'in'])->assertSessionHasNoErrors();
    expect(Attendance::query()->first()->late_minutes)->toBe(5);

    $this->post(route('my-work.leave.store'), ['type' => 'vacation', 'starts_on' => '2030-07-01', 'ends_on' => '2030-07-03'])->assertSessionHasNoErrors();
    $this->get(route('my-work.index'))->assertSee('Clock out')->assertSee('Vacation');

    // Staff cannot open the manager screens.
    $this->get(route('staff.index'))->assertForbidden();
    $this->post(route('staff.leave.decide', LeaveRequest::query()->first()->id), ['approve' => 1])->assertForbidden();

    // A member without an employee profile gets a hint, and cannot clock.
    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'staff'), $this->tenant);
    $this->get(route('my-work.index'))->assertOk()->assertSee('not linked');
    $this->post(route('my-work.clock'), ['action' => 'in'])->assertForbidden();
});

it('runs the manager screens with permissions and isolation', function () {
    PropertyManagementFixtures::login($this->owner, $this->tenant);

    $this->post(route('staff.store'), ['name' => 'Ben Reyes', 'employment_type' => 'contract', 'department_id' => $this->dept->id])->assertRedirect();
    $ben = Employee::query()->where('name', 'Ben Reyes')->firstOrFail();

    // An overnight shift: 22:00 → 06:00 next day.
    $this->post(route('staff.shifts.store'), ['employee_id' => $ben->id, 'date' => '2030-06-04', 'start' => '22:00', 'end' => '06:00'])->assertSessionHasNoErrors();
    expect(Shift::query()->where('employee_id', $ben->id)->first()->ends_at->toDateTimeString())->toBe('2030-06-05 06:00:00');

    $this->get(route('staff.index'))->assertOk()->assertSee('Maria Santos')->assertSee('Ben Reyes');
    $this->get(route('staff.schedule', ['week' => '2030-06-03']))->assertOk()->assertSee('10:00 PM–6:00 AM');
    $this->post(route('staff.attendance.clock', $ben->id), ['action' => 'in'])->assertSessionHasNoErrors();
    $this->get(route('staff.attendance'))->assertOk()->assertSee('Ben Reyes');
    $this->get(route('staff.show', $this->maria->id))->assertOk()->assertSee('Access: Staff');

    // Front desk sees but cannot change.
    PropertyManagementFixtures::login(MarketplaceFixtures::member($this->tenant, 'front_desk'), $this->tenant);
    $this->get(route('staff.schedule'))->assertOk();
    $this->post(route('staff.shifts.store'), ['employee_id' => $ben->id, 'date' => '2030-06-06', 'start' => '08:00', 'end' => '12:00'])->assertForbidden();

    // Another business: its own empty staff list; our records are 404.
    [$ownerB, $tenantB] = MarketplaceFixtures::business('Hotel B');
    app(ModuleService::class)->enableForTenant(Module::query()->where('slug', 'workforce')->firstOrFail(), $tenantB);
    PropertyManagementFixtures::login($ownerB, $tenantB);
    $this->get(route('staff.index'))->assertOk()->assertDontSee('Maria Santos');
    $this->get(route('staff.show', $this->maria->id))->assertNotFound();
    $this->post(route('staff.attendance.clock', $this->maria->id), ['action' => 'in'])->assertNotFound();
});
