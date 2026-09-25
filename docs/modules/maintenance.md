# maintenance

STATUS: COMPLETE — Phase 16 (Maintenance). Verified 2026-09-25: full Pest suite green (197 tests / 1008 assertions).

Code: `App\Modules\Maintenance`. It is part of the **workforce** module (`module.active:workforce`). Tickets were introduced in Phase 15 so housekeeping could report issues; this phase adds the workflow.

## Tickets (`maintenance_tickets`)

- Reference `MT…`, property, optional room, title, description.
- **Category**: plumbing, electrical, hvac, furniture, appliance, structural, it, other.
- **Priority**: low, normal, high, urgent. Lists sort urgent first.
- **Assigned staff**: an active member of the business, who gets a database notification (`TicketAssigned`).
- **Cost** (₱, managers), started / resolved / closed times, and the `room_out_of_order` flag.
- **Notes** (`maintenance_ticket_notes`): a comment thread. System notes record every status, assignment, cost and attachment change.
- **Attachments**: jpg / png / webp / pdf up to 5 MB, stored on the private `local` disk (`maintenance/{tenant}/{ticket}/…`) as `media` rows. They download only through `maintenance.attachments.show`, which checks permission and tenant.

## Workflow

```text
open → in_progress ⇄ on_hold → resolved → closed
                     resolved → in_progress (reopen)
```

- Starting an unassigned ticket claims it. Workers progress only their own tickets; managers can progress any.
- Closing, assigning and costing need `maintenance.manage`. Closed tickets are read-only (no notes, files or cost).
- **Room hand-back**: when the **last** active ticket of a room in `maintenance` / `out_of_order` is resolved, the room becomes `dirty`. Housekeeping then cleans and inspects it, and it is sellable again.
- Housekeeping's "Report issue" goes through `MaintenanceService::open()`.

## Screens / permissions

- `/dashboard/maintenance`: list with status (active by default) / priority / mine filters, and a new-ticket form (property → room).
- `/dashboard/maintenance/{ref}`: details, notes, files, status buttons, assign, cost. Linked in the sidebar.

| Permission | Roles |
|---|---|
| `maintenance.view` | owner, manager, front desk, staff |
| `maintenance.work` (open, notes, files, progress) | owner, manager, front desk, staff |
| `maintenance.manage` (assign, cost, close) | owner, manager |

## Tests

`tests/Feature/MaintenanceTest.php` (4 tests): full HTTP workflow with the note trail, notification, manager-only close / cost / assign, closed read-only; room hand-back only after the last ticket; private attachments (type check, stored on local, foreign business 404); ownership, member-only assignment, room/property consistency, claim on start.
