<?php

namespace App\Modules\Workforce\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Housekeeping\Models\HousekeepingTask;
use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Workforce\Models\Employee;
use App\Modules\Workforce\Models\LeaveRequest;
use App\Modules\Workforce\Services\WorkforceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Self-service for staff (Phase 17): my shifts, my housekeeping tasks and
 * maintenance tickets, clock in / out, and leave requests. Needs only an
 * employee record linked to the signed-in account in this business.
 */
class MyWorkController extends Controller
{
    public function __construct(private readonly WorkforceService $workforce) {}

    public function index(Request $request)
    {
        $employee = Employee::forUser($request->user())?->load('openAttendance');
        $userId = $request->user()->id;

        return view('workforce::my-work', [
            'employee' => $employee,
            'shifts' => $employee ? $employee->shifts()->scheduled()->whereBetween('starts_at', [today(), today()->addDays(7)])->with('property')->get() : collect(),
            'tasks' => HousekeepingTask::query()->where('assigned_to', $userId)->open()->with('room')->orderBy('due_on')->get(),
            'tickets' => MaintenanceTicket::query()->where('assigned_to', $userId)->active()->with('room')->latest()->get(),
            'leave' => $employee ? $employee->leaveRequests()->limit(10)->get() : collect(),
            'title' => 'My work',
        ]);
    }

    public function clock(Request $request)
    {
        $employee = $this->me($request);

        $request->validate(['action' => ['required', 'in:in,out']])['action'] === 'in'
            ? $this->workforce->clockIn($employee, $request->user())
            : $this->workforce->clockOut($employee, $request->user());

        return back()->with('success', 'Clocked '.$request->input('action').'.');
    }

    public function requestLeave(Request $request)
    {
        $employee = $this->me($request);

        $validated = $request->validate([
            'type' => ['required', Rule::in(LeaveRequest::TYPES)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workforce->requestLeave($employee, $validated['type'], $validated['starts_on'], $validated['ends_on'], $validated['reason'] ?? null);

        return back()->with('success', 'Leave requested.');
    }

    public function cancelLeave(Request $request, string $leave)
    {
        $this->workforce->cancelLeave($this->me($request)->leaveRequests()->findOrFail($leave));

        return back()->with('success', 'Leave request withdrawn.');
    }

    private function me(Request $request): Employee
    {
        $employee = Employee::forUser($request->user());
        abort_unless($employee !== null, 403, 'Your account is not linked to an employee profile.');

        return $employee;
    }
}
