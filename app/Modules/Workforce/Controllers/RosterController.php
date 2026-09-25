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

/** Manager side of scheduling (Phase 17): weekly roster, attendance board, leave approvals. */
class RosterController extends Controller
{
    public function __construct(private readonly WorkforceService $workforce) {}

    public function schedule(Request $request)
    {
        $this->authorizeTo($request, 'staff.view');

        $weekStart = rescue(fn () => CarbonImmutable::parse((string) $request->query('week', today()->toDateString())), today()->toImmutable(), false)->startOfWeek();
        $weekEnd = $weekStart->addDays(7);

        return view('workforce::roster.schedule', [
            'weekStart' => $weekStart,
            'days' => collect(range(0, 6))->map(fn ($i) => $weekStart->addDays($i)),
            'employees' => Employee::query()->active()->with('position')->orderBy('name')->get(),
            'shifts' => Shift::query()->scheduled()->overlapping($weekStart, $weekEnd)->get()->groupBy(fn ($s) => $s->employee_id.'|'.$s->starts_at->toDateString()),
            'leave' => LeaveRequest::query()->where('status', LeaveRequest::APPROVED)->covering($weekStart->toDateString(), $weekEnd->toDateString())->get(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'title' => 'Schedule',
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

        return view('workforce::roster.attendance', [
            'date' => $date,
            'employees' => Employee::query()->active()->with('openAttendance')->orderBy('name')->get(),
            'records' => Attendance::query()->with(['employee', 'shift'])->whereBetween('clock_in_at', [$date, $date->endOfDay()])->orderBy('clock_in_at')->get(),
            'title' => 'Attendance',
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

        return view('workforce::roster.leave', [
            'pending' => LeaveRequest::query()->where('status', LeaveRequest::PENDING)->with('employee')->orderBy('starts_on')->get(),
            'recent' => LeaveRequest::query()->where('status', '!=', LeaveRequest::PENDING)->with('employee')->latest('decided_at')->limit(20)->get(),
            'title' => 'Leave',
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
