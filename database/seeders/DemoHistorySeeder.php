<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderService;
use App\Modules\Pos\Services\PosService;
use App\Modules\PropertyManagement\Models\Room;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\RestaurantManagement\Models\RestaurantTable;
use App\Modules\Workforce\Models\Department;
use App\Modules\Workforce\Models\Employee;
use App\Modules\Workforce\Models\LeaveRequest;
use App\Modules\Workforce\Models\Position;
use App\Modules\Workforce\Services\WorkforceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Makes each demo business look like it has been trading for months:
 *
 *  - a full team (about 20 people across seven departments), most with a
 *    login and the matching role, all on a rota two weeks back and ahead,
 *    with clock-in history, lateness and leave requests;
 *  - ~90 days of hotel stays (arrive → check in → check out, some
 *    cancellations and no-shows) and ~60 days of restaurant tickets rung
 *    up on the register in daily sessions, all through the real services
 *    (clock travel), so occupancy, ADR, RevPAR, covers and revenue reports
 *    have history;
 *  - housekeeping history: past check-out cleans done by named attendants.
 *
 * Every step is idempotent. Called per tenant by DemoOperationsSeeder.
 * Development only.
 */
class DemoHistorySeeder
{
    /** [department, position, hourly rate, tenant role slug|null, headcount, shift pattern]. */
    private const ROSTER = [
        ['Management', 'General Manager', 420, 'manager', 1, 'office'],
        ['Management', 'Front Office Manager', 260, 'manager', 1, 'office'],
        ['Management', 'Restaurant Manager', 250, 'manager', 1, 'late'],
        ['Front Office', 'Guest Relations Officer', 110, 'front_desk', 2, 'rotating'],
        ['Front Office', 'Reservations Agent', 105, 'front_desk', 1, 'office'],
        ['Front Office', 'Night Auditor', 120, 'front_desk', 1, 'night'],
        ['Housekeeping', 'Executive Housekeeper', 180, 'staff', 1, 'early'],
        ['Housekeeping', 'Room Attendant', 95, 'staff', 3, 'early'],
        ['Housekeeping', 'Laundry Attendant', 90, null, 1, 'early'],
        ['Kitchen', 'Head Chef', 300, 'staff', 1, 'late'],
        ['Kitchen', 'Line Cook', 105, 'staff', 2, 'rotating'],
        ['Service', 'Server', 95, 'staff', 2, 'late'],
        ['Service', 'Bartender', 100, null, 1, 'late'],
        ['Maintenance', 'Maintenance Technician', 115, 'staff', 1, 'early'],
        ['Finance', 'Accountant', 220, 'manager', 1, 'office'],
    ];

    private const SHIFTS = [
        'office' => [['09:00', '18:00']],
        'early' => [['07:00', '15:00']],
        'late' => [['14:00', '22:00']],
        'rotating' => [['06:00', '14:00'], ['14:00', '22:00']],
        'night' => [['22:00', '06:00']],
    ];

    private const FIRST = ['Andrea', 'Bea', 'Carlo', 'Diego', 'Elaine', 'Franco', 'Gabriel', 'Hazel', 'Ivan', 'Jasmine', 'Kristine', 'Leo', 'Mikaela', 'Nico', 'Olivia', 'Paolo', 'Queenie', 'Rico', 'Sofia', 'Tomas', 'Ursula', 'Vince', 'Wena', 'Xander', 'Ysabel', 'Zaldy', 'Aira', 'Benjie', 'Cielo', 'Dominic'];

    private const LAST = ['Aquino', 'Bautista', 'Castillo', 'Dela Cruz', 'Estrada', 'Flores', 'Garcia', 'Hernandez', 'Ilagan', 'Jimenez', 'Katigbak', 'Lopez', 'Macaraeg', 'Navarro', 'Ocampo', 'Pascual', 'Quizon', 'Ramos', 'Santos', 'Tolentino', 'Umali', 'Villanueva', 'Yap', 'Zamora', 'Abad', 'Buenaventura', 'Cruz', 'Dizon', 'Evangelista', 'Fajardo'];

    private const GUESTS = ['Hannah Lee', 'Marco Rossi', 'Aiko Tanaka', 'Ben Carter', 'Chloe Martin', 'Daniel Kim', 'Emma Novak', 'Felix Wagner', 'Grace Tan', 'Hugo Silva', 'Isla Brown', 'Jonas Berg', 'Kara Singh', 'Lucas Moreau', 'Maya Patel', 'Noah Fischer', 'Olga Petrova', 'Pedro Alves', 'Rina Sato', 'Sam Wilson', 'Tessa Clarke', 'Umar Khan', 'Vera Lind', 'Will Chen', 'Yuki Mori', 'Zoe Adams', 'Ramon Cruz', 'Lea Santos', 'Joy Mendoza', 'Paul Garcia'];

    public function __construct(
        private readonly int $stayDays = 90,
        private readonly int $ticketDays = 60,
    ) {}

    public function forTenant(Tenant $tenant, User $owner, int $index): void
    {
        mt_srand(crc32($tenant->slug));

        $team = $this->roster($tenant, $owner, $index);
        $this->rotaAndAttendance($team, $owner);
        $this->leave($team, $owner);

        // Each generator runs once per business; the flag lives on the tenant so a
        // day with no successful reservation can't cause a second pass.
        $done = (array) ($tenant->settings['demo_seeded'] ?? []);

        foreach (Property::query()->orderBy('id')->get() as $property) {
            isset($done['stays']) || $this->stays($property);
            isset($done['live']) || $this->live($property);
        }

        foreach (Restaurant::query()->orderBy('id')->get() as $restaurant) {
            isset($done['tickets']) || $this->tickets($restaurant, $team);
        }

        $tenant->forceFill(['settings' => array_merge((array) $tenant->settings, [
            'demo_seeded' => ['stays' => true, 'live' => true, 'tickets' => true],
        ])])->save();

        $this->housekeeping($team);
        Carbon::setTestNow();
    }

    /** One login that owns every demo business: the portfolio view. */
    public static function groupOwner(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'group@coverandkeys.example.test'],
            ['name' => 'Isabel Reyes-Lim', 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()],
        );

        foreach (Tenant::query()->whereIn('slug', ['aplaya-beach-resort', 'kalye-suite-company', 'nido-cove-escapes'])->get() as $tenant) {
            $tenant->users()->syncWithoutDetaching([$user->id => ['status' => 'active', 'joined_at' => now()]]);
            $role = Role::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'owner')->first();

            if ($role) {
                $user->assignTenantRole($tenant, $role);
            }
        }
    }

    // ------------------------------------------------------------------
    // Team
    // ------------------------------------------------------------------

    /** @return list<array{employee: Employee, user: ?User, role: ?string, pattern: string, position: string}> */
    private function roster(Tenant $tenant, User $owner, int $index): array
    {
        $service = app(WorkforceService::class);
        $propertyId = Property::query()->orderBy('id')->value('id');
        $domain = Str::slug($tenant->name).'.example.test';
        $team = [];
        $n = 0;

        foreach (self::ROSTER as [$departmentName, $positionName, $rate, $roleSlug, $count, $pattern]) {
            $department = Department::query()->firstOrCreate(['name' => $departmentName]);
            $position = Position::query()->firstOrCreate(['name' => $positionName, 'department_id' => $department->id], ['hourly_rate' => $rate]);
            $existing = Employee::query()->where('position_id', $position->id)->orderBy('id')->get();

            for ($k = 0; $k < $count; $k++, $n++) {
                // Distinct names per business: walk the pools with a per-tenant offset.
                $name = self::FIRST[($n + $index * 7) % count(self::FIRST)].' '.self::LAST[($n * 7 + $index * 11) % count(self::LAST)];
                $email = Str::slug($name, '.').'@'.$domain;
                $user = null;

                if ($roleSlug) {
                    $user = User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()]);
                    $tenant->users()->syncWithoutDetaching([$user->id => ['status' => 'active', 'joined_at' => now()]]);
                    $role = $tenant->roles()->where('slug', $roleSlug)->first();

                    if ($role) {
                        $user->assignTenantRole($tenant, $role);
                    }
                }

                $attributes = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => '+63 917 '.(200 + $index * 40 + $n).' '.(1000 + ($n * 137) % 9000),
                    'department_id' => $department->id,
                    'position_id' => $position->id,
                    'property_id' => $propertyId,
                    'employment_type' => $count > 1 && $k === $count - 1 ? 'part_time' : 'full_time',
                    'user_id' => $user?->id,
                ];

                // Older demo databases already have some of these people: reuse and rename them.
                $employee = $existing[$k] ?? Employee::query()->where('email', $email)->first();

                if ($employee) {
                    $employee->forceFill($attributes)->save();
                } else {
                    $employee = $service->hire($attributes + ['hire_date' => today()->subMonths(3 + ($n * 5) % 40)->toDateString()], $owner);
                }

                $team[] = ['employee' => $employee, 'user' => $user, 'role' => $roleSlug, 'pattern' => $pattern, 'position' => $positionName];
            }
        }

        return $team;
    }

    /** Shifts from two weeks back to two weeks ahead; past shifts get real clock-ins (some late, a few missed). */
    private function rotaAndAttendance(array $team, User $owner): void
    {
        $service = app(WorkforceService::class);
        $from = today()->subDays(14);

        foreach ($team as $t => $member) {
            $employee = $member['employee'];

            if ($employee->shifts()->where('starts_at', '>=', $from)->where('starts_at', '<', today())->exists()) {
                continue;
            }

            foreach (range(-14, 13) as $day) {
                if (($day + $t) % 7 === 0 || ($member['pattern'] === 'office' && today()->addDays($day)->isWeekend())) {
                    continue; // day off
                }

                $options = self::SHIFTS[$member['pattern']];
                [$start, $end] = $options[intdiv($day + 14, 7) % count($options)];
                $date = today()->addDays($day);
                $startsAt = $date->copy()->setTimeFromTimeString($start);
                $endsAt = $date->copy()->setTimeFromTimeString($end);
                $endsAt = $endsAt->lte($startsAt) ? $endsAt->addDay() : $endsAt;

                $shift = rescue(fn () => $service->scheduleShift($employee, $startsAt->toDateTimeString(), $endsAt->toDateTimeString(), $owner, $employee->property_id), report: false);

                if (! $shift || $day >= 0 || mt_rand(1, 25) === 1) {
                    continue; // future shift, or a no-show
                }

                Carbon::setTestNow($startsAt->copy()->addMinutes(mt_rand(1, 10) === 1 ? mt_rand(6, 25) : -mt_rand(0, 10)));
                rescue(fn () => $service->clockIn($employee), report: false);
                Carbon::setTestNow($endsAt->copy()->addMinutes(mt_rand(0, 25)));
                rescue(fn () => $service->clockOut($employee), report: false);
                Carbon::setTestNow();
            }
        }
    }

    private function leave(array $team, User $owner): void
    {
        if (LeaveRequest::query()->exists()) {
            return;
        }

        $service = app(WorkforceService::class);
        $plan = [[3, 'vacation', 9, 11, true], [8, 'sick', 2, 2, true], [12, 'vacation', 20, 24, null], [15, 'emergency', 5, 5, false]];

        foreach ($plan as [$who, $type, $start, $end, $decision]) {
            $employee = $team[$who % count($team)]['employee'];
            $leave = rescue(fn () => $service->requestLeave($employee, $type, today()->addDays($start)->toDateString(), today()->addDays($end)->toDateString(), ucfirst($type).' leave'), report: false);

            if ($leave && $decision !== null) {
                rescue(fn () => $service->decideLeave($leave, $decision, $owner, $decision ? 'Covered by the team.' : 'Peak weekend, please pick other dates.'), report: false);
            }
        }
    }

    // ------------------------------------------------------------------
    // Hotel history
    // ------------------------------------------------------------------

    /** ~90 days of stays through the booking engine, busier at weekends. */
    private function stays(Property $property): void
    {
        if (Booking::query()->where('property_id', $property->id)->where('guest_email', 'like', '%@guestmail.example.test')->exists()) {
            return;
        }

        $types = $property->roomTypes()->active()->get();

        if ($types->isEmpty()) {
            return;
        }

        $service = app(BookingService::class);
        $sources = [Booking::SOURCE_MARKETPLACE, Booking::SOURCE_MARKETPLACE, Booking::SOURCE_MANUAL, Booking::SOURCE_WALK_IN];

        for ($d = -$this->stayDays; $d <= -2; $d++) {
            $day = today()->addDays($d);
            // At least one arrival on day one (empty calendar), so the idempotency marker always exists.
            $arrivals = max($d === -$this->stayDays ? 1 : 0, mt_rand(0, 2) + ($day->isFriday() || $day->isSaturday() ? 1 : 0));

            for ($a = 0; $a < $arrivals; $a++) {
                $nights = min(mt_rand(1, 4), -$d - 1);
                $guest = self::GUESTS[mt_rand(0, count(self::GUESTS) - 1)];
                $source = $sources[mt_rand(0, count($sources) - 1)];
                $fate = mt_rand(1, 100);

                Carbon::setTestNow($day->copy()->setTime(9 + $a, 15));

                $booking = rescue(fn () => $service->reserve($property, [
                    'check_in' => $day->toDateString(),
                    'check_out' => $day->copy()->addDays($nights)->toDateString(),
                    'rooms' => [['room_type_id' => $types[mt_rand(0, $types->count() - 1)]->id, 'quantity' => 1]],
                    'adults' => mt_rand(1, 2),
                    'children' => mt_rand(1, 5) === 1 ? 1 : 0,
                    'guest_name' => $guest,
                    'guest_email' => Str::slug($guest, '.').'@guestmail.example.test',
                    'guest_phone' => '+63 918 '.mt_rand(100, 999).' '.mt_rand(1000, 9999),
                ], $source), report: false);

                if (! $booking) {
                    continue; // sold out that night
                }

                if ($booking->status === Booking::PENDING) {
                    $service->transition($booking, Booking::CONFIRMED);
                }

                if ($fate <= 7 && $booking->status !== Booking::CHECKED_IN) {
                    $service->transition($booking->refresh(), Booking::CANCELLED, 'Plans changed');
                    continue;
                }

                if ($fate <= 10 && $booking->status !== Booking::CHECKED_IN) {
                    Carbon::setTestNow($day->copy()->setTime(23, 30));
                    $service->transition($booking->refresh(), Booking::NO_SHOW);
                    continue;
                }

                if ($booking->status !== Booking::CHECKED_IN) {
                    Carbon::setTestNow($day->copy()->setTime(14, mt_rand(0, 59)));
                    $service->transition($booking->refresh(), Booking::CHECKED_IN);
                }

                Carbon::setTestNow($day->copy()->addDays($nights)->setTime(11, mt_rand(0, 40)));
                $service->transition($booking->refresh(), Booking::CHECKED_OUT);
            }
        }

        Carbon::setTestNow();
    }

    /**
     * Today looks like a working day: guests in house (arrived in the last
     * few days), departures and arrivals due today, and a month of upcoming
     * reservations. Skipped once a live stay from this seeder exists.
     */
    private function live(Property $property): void
    {
        if (Booking::query()->where('property_id', $property->id)->where('guest_email', 'like', '%@guestmail.example.test')->where('check_out', '>=', today())->exists()) {
            return;
        }

        $types = $property->roomTypes()->active()->get();

        if ($types->isEmpty()) {
            return;
        }

        $service = app(BookingService::class);
        $lastDay = app()->runningUnitTests() ? 3 : 30;

        for ($d = -3; $d <= $lastDay; $d++) {
            $day = today()->addDays($d);
            $arrivals = $d <= 0 ? mt_rand(1, 2) : mt_rand(0, 2) + ($day->isFriday() || $day->isSaturday() ? 1 : 0);

            for ($a = 0; $a < $arrivals; $a++) {
                $nights = max(mt_rand(1, 4), -$d); // stays that started earlier are still open today
                $guest = self::GUESTS[mt_rand(0, count(self::GUESTS) - 1)];
                $source = $d > 0 && mt_rand(1, 4) === 1 ? Booking::SOURCE_MARKETPLACE : Booking::SOURCE_MANUAL;

                Carbon::setTestNow(($d < 0 ? $day : today())->copy()->setTime(9 + $a, 5));

                $booking = rescue(fn () => $service->reserve($property, [
                    'check_in' => $day->toDateString(),
                    'check_out' => $day->copy()->addDays($nights)->toDateString(),
                    'rooms' => [['room_type_id' => $types[mt_rand(0, $types->count() - 1)]->id, 'quantity' => 1]],
                    'adults' => mt_rand(1, 2),
                    'guest_name' => $guest,
                    'guest_email' => Str::slug($guest, '.').'@guestmail.example.test',
                    'guest_phone' => '+63 918 '.mt_rand(100, 999).' '.mt_rand(1000, 9999),
                    'special_requests' => mt_rand(1, 5) === 1 ? ['Late arrival, around 10 pm', 'Celebrating an anniversary', 'High floor if possible', 'Airport pick-up please'][mt_rand(0, 3)] : null,
                ], $source), report: false);

                // Arrived earlier (or checked in early today): in house now.
                if ($booking && ($d < 0 || ($d === 0 && mt_rand(1, 3) === 1))) {
                    Carbon::setTestNow($day->copy()->setTime(14, mt_rand(0, 59)));
                    $service->transition($booking, Booking::CHECKED_IN);
                }
            }
        }

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------
    // Restaurant history
    // ------------------------------------------------------------------

    /** Daily register sessions with table tickets, paid by cash / card / e-wallet. */
    private function tickets(Restaurant $restaurant, array $team): void
    {
        if (\App\Modules\Pos\Models\PosSession::query()->where('restaurant_id', $restaurant->id)->where('opened_at', '<', today())->exists()) {
            return;
        }

        $items = MenuItem::query()->where('restaurant_id', $restaurant->id)->pluck('id');
        $tables = RestaurantTable::query()->where('restaurant_id', $restaurant->id)->where('status', RestaurantTable::STATUS_ACTIVE)->get();
        $staff = collect($team)->filter(fn ($m) => $m['user'] && in_array($m['position'], ['Restaurant Manager', 'Server', 'Head Chef'], true))->pluck('user')->values();

        if ($items->count() < 2 || $staff->isEmpty()) {
            return;
        }

        $orders = app(OrderService::class);
        $pos = app(PosService::class);
        $methods = ['cash', 'cash', 'card', 'card', 'ewallet'];

        for ($d = -$this->ticketDays; $d <= -1; $d++) {
            $day = today()->addDays($d);
            $cashier = $staff[($d + 1000) % $staff->count()];

            if ($open = $pos->currentSession($restaurant)) {
                $pos->closeSession($open, $open->cashInDrawer(), $cashier);
            }

            Carbon::setTestNow($day->copy()->setTime(10, 30));
            $session = $pos->openSession($restaurant, 3000, $cashier);
            $count = mt_rand(6, 11) + ($day->isWeekend() ? 5 : 0);

            for ($i = 0; $i < $count; $i++) {
                Carbon::setTestNow($day->copy()->setTime(11 + intdiv($i * 10, $count), mt_rand(0, 59)));
                $lines = [];
                foreach (range(1, mt_rand(2, 4)) as $l) {
                    $lines[] = ['item_id' => $items[mt_rand(0, $items->count() - 1)], 'quantity' => mt_rand(1, 3)];
                }

                $table = $tables->isNotEmpty() ? $tables[mt_rand(0, $tables->count() - 1)] : null;
                $order = rescue(fn () => $orders->placeAtRegister($restaurant, $lines, $table, $cashier), report: false);

                if (! $order) {
                    continue;
                }

                rescue(function () use ($orders, $pos, $order, $methods, $cashier) {
                    $order = $orders->transition($order->refresh(), Order::ACCEPTED);
                    Carbon::setTestNow(now()->addMinutes(mt_rand(35, 80)));
                    $method = $methods[mt_rand(0, count($methods) - 1)];
                    $due = $pos->balanceDue($order);
                    $pos->pay($order, $method, $due, $cashier, $method === 'cash' ? ceil($due / 100) * 100 : null);
                    $pos->close($order->refresh());
                }, report: false);
            }

            Carbon::setTestNow($day->copy()->setTime(22, 40));
            $session->refresh();
            $pos->closeSession($session, round($session->cashInDrawer() - (mt_rand(1, 6) === 1 ? mt_rand(20, 150) : 0), 2), $cashier);
        }

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------
    // Housekeeping
    // ------------------------------------------------------------------

    /** Past check-out cleans were done (by named attendants); today's queue is shared out. */
    private function housekeeping(array $team): void
    {
        $attendants = collect($team)->filter(fn ($m) => $m['user'] && in_array($m['position'], ['Room Attendant', 'Executive Housekeeper'], true))->pluck('user')->values();

        if ($attendants->isEmpty()) {
            return;
        }

        HousekeepingTask::query()->open()->whereDate('due_on', '<', today())->orderBy('id')->get()
            ->each(function (HousekeepingTask $task, int $i) use ($attendants) {
                $start = Carbon::parse($task->due_on)->setTime(11 + $i % 4, ($i * 13) % 60);
                $task->forceFill([
                    'status' => HousekeepingTask::COMPLETED,
                    'assigned_to' => $attendants[$i % $attendants->count()]->id,
                    'started_at' => $start,
                    'completed_at' => $start->copy()->addMinutes(25 + $i % 20),
                ])->save();
            });

        HousekeepingTask::query()->open()->whereNull('assigned_to')->whereDate('due_on', today())->orderBy('id')->get()
            ->each(fn (HousekeepingTask $task, int $i) => $task->forceFill(['assigned_to' => $attendants[$i % $attendants->count()]->id])->save());

        // Rooms whose check-out clean is done are ready again.
        $openRooms = HousekeepingTask::query()->open()->pluck('room_id');
        Room::query()->where('housekeeping_status', Room::HK_DIRTY)->whereNotIn('id', $openRooms)
            ->update(['housekeeping_status' => Room::HK_CLEAN, 'housekeeping_updated_at' => now()]);
    }
}
