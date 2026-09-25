# staff

STATUS: COMPLETE — Phase 17 (Staff management). Verified 2026-09-26: full Pest suite green (203 tests / 1066 assertions).

Code: `App\Modules\Workforce`, in the **workforce** module (`module.active:workforce`). It shares that module with Housekeeping (Phase 15) and Maintenance (Phase 16).

## Records

| Model | Table | Notes |
|---|---|---|
| `Department` | `departments` | Unique name per business. |
| `Position` | `positions` | Optional department, default hourly rate. |
| `Employee` | `employees` | `EMP-0001` numbering per business. Department, position, home property, employment type (full / part time, contract), status (active / terminated). Optional **`user_id`**: an active member account of this business, at most one employee per account. The account carries the tenant role (**permissions**), shown on the profile with a link to Team. |
| `Shift` | `shifts` | `[starts_at, ends_at)` per employee, optional property, `scheduled` / `cancelled`. |
| `Attendance` | `attendances` | Clock in / out, matched shift, minutes worked, late minutes, who recorded it. |
| `LeaveRequest` | `leave_requests` | vacation / sick / emergency / unpaid, inclusive dates, pending → approved / rejected / cancelled, decider + note. |

## Rules (`WorkforceService`)

- **Shifts**: 15 minutes to 16 hours, active employees only. Never overlapping another scheduled shift of the same person, and never on approved leave. Roster edits for one employee are serialised with a row lock. From the form, an end time before the start runs past midnight.
- **Attendance**: one open clock-in at a time (row-locked). A clock-in matches the scheduled shift that covers now or starts within 2 hours. Late minutes are counted from that shift's start. With no shift it is unscheduled.
- **Leave**: no overlap with the person's pending or approved leave. **Approval cancels their scheduled shifts inside the range** (the count is reported). Pending requests can be withdrawn. Decisions are audited.

## Screens / permissions

- `/dashboard/staff`: employees, add form, departments, positions. `/staff/{id}`: profile, upcoming shifts, attendance, leave.
- `/dashboard/staff/schedule?week=`: weekly roster (employees × days, leave badges), add or cancel shift.
- `/dashboard/staff/attendance?date=`: day log, clock someone in or out.
- `/dashboard/staff/leave`: approve / reject pending requests, recent decisions.
- `/dashboard/my-work`: for anyone with a linked employee record. Shows the next 7 days of shifts, assigned housekeeping tasks and maintenance tickets (the **tasks** view), a clock in / out button, and leave request and withdraw.

| Permission | Roles |
|---|---|
| `staff.view` | owner, manager, front desk |
| `staff.manage`, `schedules.manage`, `attendance.manage`, `leave.approve` | owner, manager |

The sidebar shows "Staff" to viewers and "My work" to everyone else while the workforce module is active.

## Tests

`tests/Feature/WorkforceTest.php` (6 tests): hiring and account-link rules; roster overlap, length, back-to-back, cancel frees the slot, terminated staff; clock-in lateness, double clock-in, early-shift matching, unscheduled; leave overlap, approval cancelling shifts, blocking new shifts, reject, withdraw; "My work" (shifts, tasks, clock, leave, unlinked account); manager screens including overnight shift, front desk read-only, tenant isolation.
