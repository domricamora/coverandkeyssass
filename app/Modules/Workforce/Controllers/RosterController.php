<?php

namespace App\Modules\Workforce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Workforce\Models\Attendance;
use App\Modules\Workforce\Models\Employee;
use App\Modules\Workforce\Models\LeaveRequest;
use App\Modules\Workforce\Models\Shift;
use App\Modules\Workforce\Services\WorkforceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Manager side of scheduling (Phase 17): weekly roster, attendance board, leave approvals. */
class RosterController extends Controller
{
    public function __construct(private readonly WorkforceService $workforce) {}

    public function schedule(Request $request)
    {
        $this->authorizeTo($request, 'staff.view');

        $weekStart = rescue(fn () => CarbonImmutable::parse((string) $request->query('week', today()->toDateString())), today()->toImmutable(), false)->startOfWeek();
        $weekEnd = $weekStart->addDays(7);

        $days = collect(range(0, 6))->map(fn ($i) => $weekStart->addDays($i));
        $employees = Employee::query()->active()->with('position')->orderBy('name')->get();
        $shifts = Shift::query()->scheduled()->overlapping($weekStart, $weekEnd)->get()->groupBy(fn ($s) => $s->employee_id.'|'.$s->starts_at->toDateString());
        $leave = LeaveRequest::query()->where('status', LeaveRequest::APPROVED)->covering($weekStart->toDateString(), $weekEnd->toDateString())->get();
        $canManage = $request->user()->hasPermissionTo('schedules.manage');

        return Inertia::render('Staff/Schedule', [
            'week' => [
                'label' => 'Week of '.$weekStart->format('M j, Y'),
                'start' => $weekStart->toDateString(),
                'prev' => route('staff.schedule', ['week' => $weekStart->subWeek()->toDateString()]),
                'next' => route('staff.schedule', ['week' => $weekStart->addWeek()->toDateString()]),
                'now' => route('staff.schedule'),
            ],
            'days' => $days->map(fn ($d) => ['date' => $d->toDateString(), 'label' => $d->format('D j'), 'today' => $d->isToday()]),
            'rows' => $employees->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'position' => $e->position?->name,
                'cells' => $days->map(fn ($d) => [
                    'leave' => ($l = $leave->first(fn ($l) => $l->employee_id === $e->id && $l->starts_on->lte($d) && $l->ends_on->gte($d))) ? ucfirst($l->type).' leave' : null,
                    'shifts' => $shifts->get($e->id.'|'.$d->toDateString(), collect())->map(fn ($s) => [
                        'id' => $s->id,
                        'label' => $s->label(),
                        'cancel' => $canManage ? route('staff.shifts.cancel', $s->id) : null,
                    ])->values(),
                ]),
            ]),
            'employees' => $employees->map(fn ($e) => [$e->id, $e->name]),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => [$p->id, $p->name]),
            'can' => ['manage' => $canManage],
            'tabs' => StaffController::tabs('staff.schedule'),
            'urls' => ['store' => route('staff.shifts.store')],
        ]);
    }

    public function storeShift(Request $request)
    {
        $this->authorizeTo($request, 'schedules.manage');

        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i'],
            'property_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // An end time before the start runs past midnight.
        $starts = CarbonImmutable::parse($validated['date'].' '.$validated['start']);
        $ends = CarbonImmutable::parse($validated['date'].' '.$validated['end']);
        if ($ends->lte($starts)) {
            $ends = $ends->addDay();
        }

        $this->workforce->scheduleShift(
            Employee::query()->findOrFail($validated['employee_id']),
            $starts->toDateTimeString(), $ends->toDateTimeString(), $request->user(),
            isset($validated['property_id']) ? Property::query()->findOrFail($validated['property_id'])->id : null,
            $validated['notes'] ?? null,
        );

        return back()->with('success', 'Shift scheduled.');
    }

    public function cancelShift(Request $request, string $shift)
    {
        $this->authorizeTo($request, 'schedules.manage');
        $this->workforce->cancelShift(Shift::query()->findOrFail($shift));

        return back()->with('success', 'Shift cancelled.');
    }

    public function attendance(Request $request)
    {
        $this->authorizeTo($request, 'staff.view');

        $date = rescue(fn () => CarbonImmutable::parse((string) $request->query('date', today()->toDateString())), today()->toImmutable(), false)->startOfDay();

        $employees = Employee::query()->active()->with('openAttendance')->orderBy('name')->get();

        return Inertia::render('Staff/Attendance', [
            'date' => $date->toDateString(),
            'label' => $date->format('l, M j, Y'),
            'onClock' => $employees->filter(fn ($e) => $e->openAttendance)->count(),
            'records' => Attendance::query()->with(['employee', 'shift'])->whereBetween('clock_in_at', [$date, $date->endOfDay()])->orderBy('clock_in_at')->get()->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->employee?->name,
                'shift' => $a->shift?->label() ?? 'unscheduled',
                'in' => $a->clock_in_at->format('g:i A'),
                'out' => $a->clock_out_at?->format('g:i A'),
                'worked' => $a->hoursLabel(),
                'late' => $a->late_minutes ?: null,
            ]),
            'employees' => $employees->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'since' => $e->openAttendance?->clock_in_at->format('g:i A'),
                'clock' => route('staff.attendance.clock', $e->id),
            ]),
            'can' => ['manage' => $request->user()->hasPermissionTo('attendance.manage')],
            'tabs' => StaffController::tabs('staff.attendance'),
            'urls' => ['self' => route('staff.attendance')],
        ]);
    }

    public function clock(Request $request, string $employee)
    {
        $this->authorizeTo($request, 'attendance.manage');
        $employee = Employee::query()->findOrFail($employee);

        $request->validate(['action' => ['required', 'in:in,out']])['action'] === 'in'
            ? $this->workforce->clockIn($employee, $request->user())
            : $this->workforce->clockOut($employee, $request->user());

        return back()->with('success', $employee->name.' clocked '.$request->input('action').'.');
    }

    public function leave(Request $request)
    {
        $this->authorizeTo($request, 'staff.view');

        $row = fn (LeaveRequest $l) => [
            'id' => $l->id,
            'name' => $l->employee?->name,
            'type' => ucfirst($l->type),
            'dates' => $l->starts_on->format('M j').'–'.$l->ends_on->format('M j, Y'),
            'days' => $l->days(),
            'reason' => $l->reason,
            'status' => $l->status,
            'note' => $l->decision_note,
            'decide' => route('staff.leave.decide', $l->id),
        ];

        return Inertia::render('Staff/Leave', [
            'pending' => LeaveRequest::query()->where('status', LeaveRequest::PENDING)->with('employee')->orderBy('starts_on')->get()->map($row),
            'recent' => LeaveRequest::query()->where('status', '!=', LeaveRequest::PENDING)->with('employee')->latest('decided_at')->limit(20)->get()->map($row),
            'can' => ['approve' => $request->user()->hasPermissionTo('leave.approve')],
            'tabs' => StaffController::tabs('staff.leave'),
        ]);
    }

    public function decideLeave(Request $request, string $leave)
    {
        $this->authorizeTo($request, 'leave.approve');

        $validated = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:255']]);
        $cancelled = $this->workforce->decideLeave(LeaveRequest::query()->findOrFail($leave), (bool) $validated['approve'], $request->user(), $validated['note'] ?? null);

        return back()->with('success', $validated['approve'] ? 'Leave approved'.($cancelled ? ' — '.$cancelled.' shift(s) cancelled.' : '.') : 'Leave rejected.');
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
