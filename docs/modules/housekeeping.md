# housekeeping

STATUS: COMPLETE — Phase 15 (Housekeeping). Verified 2026-09-25: full Pest suite green (193 tests / 961 assertions).

Code: `App\Modules\Housekeeping`. It is part of the **workforce** module (`module.active:workforce`).

## Room status

`rooms.housekeeping_status` (default `clean`) is separate from the Phase 04 sellable `rooms.status`:

```text
dirty → cleaning → clean → inspected
            ↑________________| (failed inspection → dirty + re-clean)
maintenance   (issue reported, room still sellable)
out_of_order  (issue reported, room removed from sellable inventory)
```

`Room::scopeSellable()` now excludes `out_of_order`, so the booking engine stops selling that room at once. A manager can set any status by hand.

## Tasks (`housekeeping_tasks`)

- Types: `checkout_clean`, `stayover`, `deep_clean`, `turndown`. Priority is `normal` or `high`, with a due date, notes and an assignee (an active member of the business).
- **Check-out** (`BookingTransitioned` → `checked_out`): each room of the booking becomes dirty and gets a `checkout_clean` task due today (one per room per day).
- **Start** (pending → in_progress, room cleaning): starting an unassigned task claims it. Housekeepers can only start their own tasks; managers can start any.
- **Complete** (in_progress → completed, room clean).
- **Inspect** (managers): passed → room inspected. Failed → room dirty plus a **high-priority re-clean** for the same housekeeper, with the inspection notes.
- **Cancel** open tasks. Assignment sends a database notification (`TaskAssigned`).

## Maintenance requests

"Report issue" on a room creates a `maintenance_tickets` row (reference `MT…`, priority, reporter) and sets the room to `maintenance`, or to `out_of_order` when ticked. Phase 16 adds the ticket workflow (see `maintenance.md`), which returns the room as `dirty` when the last ticket is resolved.

## Screen / permissions

`/dashboard/housekeeping`: room status grid (per property), task list, "My tasks" filter, create / assign / start / done / pass / fail / cancel, and the open-maintenance list. Linked in the sidebar.

| Permission | Roles |
|---|---|
| `housekeeping.view` | owner, manager, front desk, staff |
| `housekeeping.work` (start / complete / report issue) | owner, manager, staff |
| `housekeeping.manage` (create / assign / inspect / set status / cancel) | owner, manager |

The `staff` role is now the housekeeping role (it had no permissions before). Rooms and tasks resolve through the tenant scope, so another business's ids return 404.

## Tests

`tests/Feature/HousekeepingTest.php` (5 tests): check-out → dirty + task; clean → inspect (fail → high-priority re-clean → pass) with notification; own/unassigned-only work, claiming, manager override, member-only assignment; out-of-order removes the room from sale (booking of the last room fails) while maintenance keeps it sellable; board, module gating, permissions and isolation.
