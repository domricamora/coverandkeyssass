<?php

namespace App\Modules\Workforce\Services;

use App\Models\User;
use App\Modules\Workforce\Models\Attendance;
use App\Modules\Workforce\Models\Employee;
use App\Modules\Workforce\Models\LeaveRequest;
use App\Modules\Workforce\Models\Shift;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff management rules (Phase 17): roster, attendance and leave.
 *
 * - A shift is 15 min – 16 h, for an active employee, never overlapping
 *   another scheduled shift of theirs, never on approved leave.
 * - Clock-in: one open attendance at a time; matched to the shift that
 *   covers now (or starts within the next 2 h); late minutes counted from
 *   the shift start.
 * - Leave: no overlap with the employee's pending / approved leave;
 *   approving it cancels their scheduled shifts inside the range.
 */
class WorkforceService
{
    public const MAX_SHIFT_HOURS = 16;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function hire(array $data, User $by): Employee
    {
        if (! empty($data['user_id'])) {
            $this->assertLinkable((int) $data['user_id']);
        }

        $employee = Employee::create($data + ['employee_no' => Employee::nextNumber()]);
        $this->audit->log('employee.hired', $employee, null, ['name' => $employee->name, 'by' => $by->id]);

        return $employee;
    }

    /** @param array<string, mixed> $data */
    public function update(Employee $employee, array $data): Employee
    {
        if (! empty($data['user_id']) && (int) $data['user_id'] !== (int) $employee->user_id) {
            $this->assertLinkable((int) $data['user_id']);
        }

        $old = $employee->only(array_keys($data));
        $employee->update($data);
        $this->audit->log('employee.updated', $employee, $old, $employee->only(array_keys($data)));

        return $employee;
    }

    public function scheduleShift(Employee $employee, string $startsAt, string $endsAt, User $by, ?int $propertyId = null, ?string $notes = null): Shift
    {
        $start = CarbonImmutable::parse($startsAt);
        $end = CarbonImmutable::parse($endsAt);
        $minutes = $start->diffInMinutes($end, false);

        match (true) {
            $employee->status !== Employee::ACTIVE => $this->fail('employee_id', $employee->name.' is not active.'),
            $minutes < 15 => $this->fail('ends_at', 'A shift ends after it starts (15 minutes minimum).'),
            $minutes > self::MAX_SHIFT_HOURS * 60 => $this->fail('ends_at', 'A shift is at most '.self::MAX_SHIFT_HOURS.' hours.'),
            default => null,
        };

        return DB::transaction(function () use ($employee, $start, $end, $by, $propertyId, $notes) {
            // Serialise roster edits for one employee.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->value('id');

            if ($employee->shifts()->scheduled()->overlapping($start, $end)->exists()) {
                $this->fail('starts_at', $employee->name.' already has a shift in that time.');
            }

            if ($employee->leaveRequests()->where('status', LeaveRequest::APPROVED)->covering($start->toDateString(), $end->toDateString())->exists()) {
                $this->fail('starts_at', $employee->name.' is on approved leave then.');
            }

            return $employee->shifts()->create([
                'property_id' => $propertyId,
                'starts_at' => $start,
                'ends_at' => $end,
                'notes' => $notes,
                'created_by' => $by->id,
            ]);
        });
    }

    public function cancelShift(Shift $shift): void
    {
        $shift->forceFill(['status' => Shift::CANCELLED])->save();
    }

    public function clockIn(Employee $employee, ?User $by = null): Attendance
    {
        if ($employee->status !== Employee::ACTIVE) {
            $this->fail('employee', $employee->name.' is not active.');
        }

        return DB::transaction(function () use ($employee, $by) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->value('id');

            if ($employee->attendances()->whereNull('clock_out_at')->exists()) {
                $this->fail('employee', $employee->name.' is already clocked in.');
            }

            $now = now();
            $shift = $employee->shifts()->scheduled()
                ->where('starts_at', '<=', $now->copy()->addHours(2))
                ->where('ends_at', '>', $now)
                ->orderBy('starts_at')
                ->first();

            return $employee->attendances()->create([
                'shift_id' => $shift?->id,
                'clock_in_at' => $now,
                'late_minutes' => $shift && $now->gt($shift->starts_at) ? (int) $shift->starts_at->diffInMinutes($now) : 0,
                'recorded_by' => $by?->id,
            ]);
        });
    }

    public function clockOut(Employee $employee, ?User $by = null): Attendance
    {
        $open = $employee->attendances()->whereNull('clock_out_at')->first();

        if (! $open) {
            $this->fail('employee', $employee->name.' is not clocked in.');
        }

        $now = now();
        $open->forceFill([
            'clock_out_at' => $now,
            'minutes_worked' => (int) $open->clock_in_at->diffInMinutes($now),
            'recorded_by' => $open->recorded_by ?? $by?->id,
        ])->save();

        return $open;
    }

    public function requestLeave(Employee $employee, string $type, string $startsOn, string $endsOn, ?string $reason): LeaveRequest
    {
        if (! in_array($type, LeaveRequest::TYPES, true)) {
            $this->fail('type', 'Pick a leave type.');
        }

        if ($endsOn < $startsOn) {
            $this->fail('ends_on', 'Leave ends on or after the day it starts.');
        }

        if ($employee->leaveRequests()->whereIn('status', [LeaveRequest::PENDING, LeaveRequest::APPROVED])->covering($startsOn, $endsOn)->exists()) {
            $this->fail('starts_on', 'This overlaps another leave request.');
        }

        return $employee->leaveRequests()->create(['type' => $type, 'starts_on' => $startsOn, 'ends_on' => $endsOn, 'reason' => $reason]);
    }

    /** Approve (cancelling the employee's shifts in the range) or reject a pending request. Returns cancelled shift count. */
    public function decideLeave(LeaveRequest $leave, bool $approve, User $by, ?string $note = null): int
    {
        if ($leave->status !== LeaveRequest::PENDING) {
            $this->fail('leave', 'This request was already decided.');
        }

        return DB::transaction(function () use ($leave, $approve, $by, $note) {
            $leave->forceFill([
                'status' => $approve ? LeaveRequest::APPROVED : LeaveRequest::REJECTED,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            $cancelled = 0;

            if ($approve) {
                $cancelled = Shift::query()->where('employee_id', $leave->employee_id)->scheduled()
                    ->overlapping($leave->starts_on->startOfDay(), $leave->ends_on->copy()->addDay()->startOfDay())
                    ->update(['status' => Shift::CANCELLED]);
            }

            $this->audit->log('leave.'.($approve ? 'approved' : 'rejected'), $leave, null, ['shifts_cancelled' => $cancelled, 'note' => $note]);

            return $cancelled;
        });
    }

    public function cancelLeave(LeaveRequest $leave): void
    {
        if ($leave->status !== LeaveRequest::PENDING) {
            $this->fail('leave', 'Only pending requests can be withdrawn.');
        }

        $leave->forceFill(['status' => LeaveRequest::CANCELLED])->save();
    }

    // ------------------------------------------------------------------

    /** The account must belong to this business and not already be someone's employee record. */
    private function assertLinkable(int $userId): void
    {
        $member = app(TenantContext::class)->tenant()?->users()->whereKey($userId)->wherePivot('status', 'active')->exists();

        if (! $member) {
            $this->fail('user_id', 'Link a member account of this business.');
        }

        if (Employee::query()->where('user_id', $userId)->exists()) {
            $this->fail('user_id', 'That account is already linked to another employee.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
