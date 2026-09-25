# crm

STATUS: COMPLETE — Phase 21 (CRM). Verified 2026-09-26: full Pest suite green (224 tests / 1284 assertions).

Code: `App\Modules\Crm`, gated by the **crm** module (`module.active:crm`). Screens live under `/dashboard/guests`.

## Contacts

`crm_contacts` holds one row per guest per business. `CrmService::sync()` (run on every CRM list view) folds every guest the business has seen into a contact: bookings (`guest_*`), food orders (`customer_*`, account email) and table reservations (`guest_*`). Guests are matched by **linked account → email (lower-cased) → phone (trimmed)**, and missing details are filled in. It is idempotent. Walk-ins with no account, email or phone stay anonymous.

Cached metrics refresh on sync and on every profile view:

- `bookings_count`: stays checked in, checked out or completed
- `orders_count`: completed food orders
- `reservations_count`: seated or completed tables
- `total_spend`: stays plus orders
- `first_seen_at`, `last_activity_at`: latest check-out, order or table

**History is not copied.** A profile reads bookings, orders and reservations live from their modules by identity (`Contact::bookingsQuery()`, and so on).

Also on each contact:

- **Tags**: per business, case-insensitive de-duplication, set as a comma list.
- **Notes**: with author.
- **Communication history** (`crm_interactions`): channel email / sms / phone / in_person / chat, inbound or outbound. System senders pass a `source_key` so retries never log twice (Marketing, Phase 22, uses this).
- **VIP flag**.
- **Marketing consent**: with a timestamp.

## Segments (`Support\Segments`)

| Segment | Rule |
|---|---|
| VIP | `is_vip` |
| Frequent Guest | ≥ 3 stays |
| Inactive | last activity > 180 days ago |
| High Spender | lifetime spend ≥ ₱20,000 |
| New Customer | first seen ≤ 30 days ago |
| Restaurant Customer | any completed order or table visit |
| Hotel Customer | any stay |

The thresholds are constants (a ponytail ceiling: make them business settings on request). The list shows every segment with a count, plus tag and search filters.

## Permissions

`crm.view`, `crm.manage` (owner, manager, front desk).

## Ceilings

- Phones are matched exactly after trimming. Normalise at capture to merge "0917 123 4567" with "09171234567".
- Sync scans all bookings, orders and reservations each run. Make it incremental when volumes grow.

## Tests

`tests/Feature/CrmTest.php` (4 tests): one contact from two stays (account + email-only), an order and a table (upper-case email), anonymous walk-in skipped, idempotent sync, metrics and spend; the seven segments (including time travel for inactive); tags de-duplicated and replaced, consent timestamp, de-duplicated system log, notes; screens with profile update, VIP segment, duplicate email refused, staff refused, module gating, tenant isolation.
