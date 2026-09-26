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
use Inertia\Inertia;

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

        return Inertia::render('MyWork/Index', [
            'employee' => $employee ? [
                'name' => $employee->name,
                'no' => $employee->employee_no,
                'since' => $employee->openAttendance?->clock_in_at->format('g:i A'),
            ] : null,
            'shifts' => $employee ? $employee->shifts()->scheduled()->whereBetween('starts_at', [today(), today()->addDays(7)])->with('property')->get()->map(fn ($s) => [
                'id' => $s->id,
                'text' => $s->starts_at->format('D, M j').' · '.$s->label().($s->property ? ' · '.$s->property->name : ''),
            ]) : [],
            'tasks' => HousekeepingTask::query()->where('assigned_to', $userId)->open()->with('room')->orderBy('due_on')->get()->map(fn ($t) => [
                'id' => $t->id,
                'text' => 'Room '.$t->room?->room_number.' · '.$t->typeLabel().' · due '.$t->due_on->format('M j'),
                'high' => $t->priority === 'high',
            ]),
            'tickets' => MaintenanceTicket::query()->where('assigned_to', $userId)->active()->with('room')->latest()->get()->map(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'text' => $t->title.($t->room ? ' · room '.$t->room->room_number : ''),
                'href' => route('maintenance.show', $t->reference),
            ]),
            'leave' => $employee ? $employee->leaveRequests()->limit(10)->get()->map(fn ($l) => [
                'id' => $l->id,
                'text' => ucfirst($l->type).' · '.$l->starts_on->format('M j').'–'.$l->ends_on->format('M j'),
                'status' => $l->status,
                'note' => $l->decision_note,
                'cancel' => $l->status === LeaveRequest::PENDING ? route('my-work.leave.cancel', $l->id) : null,
            ]) : [],
            'types' => LeaveRequest::TYPES,
            'today' => today()->toDateString(),
            'tabs' => $request->user()->hasPermissionTo('staff.view') ? StaffController::tabs('my-work.index') : [],
            'urls' => ['clock' => route('my-work.clock'), 'leave' => route('my-work.leave.store'), 'board' => route('housekeeping.index', ['mine' => 1])],
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
