# super-admin

STATUS: COMPLETE — Phase 28 (Super Admin). Verified 2026-09-26: full Pest suite green (265 tests / 1680 assertions).

Code: `App\Modules\PlatformAdmin`, plus `App\Http\Controllers\Admin\*` (Phase 01/02) and the admin screens of Wallet, Reviews, Messaging and Billing. Every route is behind `auth` + `super.admin`.

## Dashboard (`/admin`)

- Guest payments (all time and last 30 days).
- Platform commission, with the number pending release.
- Subscription revenue, with the number of open invoices.
- Bookings and food orders (all time and last 30 days).
- Hosts (businesses), with the number active.
- Customers (users with no business and no platform role).
- Properties and restaurants awaiting approval.
- Live and past-due subscriptions.
- Requested payouts.

Each tile links to its control screen. A nav bar reaches every admin screen.

## Controls

| Area | Screen | What it does |
|---|---|---|
| Users, hosts, customers | `/admin/users?type=hosts\|customers` | search, suspend / activate (Phase 01), host / customer filter |
| Businesses | `/admin/tenants` | create, suspend, delete, modules per business |
| Properties / restaurants | `/admin/listings/{properties\|restaurants}` | approve pending, publish, suspend (reason required), send back to draft; audited `platform.listing.*` |
| Bookings / orders | `/admin/bookings`, `/admin/orders` | cross-business search by reference / guest, status filter |
| Payments & refunds | `/admin/payments` | status filter; **Refund** puts the booking or order through its state machine (cancel if needed → refunded), so PayMongo, the wallet, commissions, the folio and accounting follow as they do for a host refund |
| Payouts / commissions | `/admin/payouts`, `/admin/commissions` | Phase 08 |
| Reviews / support | `/admin/reviews`, `/admin/support` | Phases 24 / 25 |
| Modules | `/admin/modules` | Phase 02 catalogue CRUD |
| Pricing | `/admin/pricing` | Monthly / Yearly price and active flag for every module plan (applies from each business's next invoice); audited |
| Subscriptions | `/admin/billing` | Phase 27 |
| CMS | `/admin/pages` → public `/pages/{slug}` | Markdown (raw HTML stripped, unsafe links dropped), draft / published, optional footer link; drafts are visible only to Super Admins |
| Settings | `/admin/settings` | support email / phone (footer), site announcement banner (public pages), default commission % (used by `CommissionRate::defaultRate()` when no rule matches) |
| Reports | `/admin/reports` | CSV exports by creation date for bookings, food orders, payments, commissions and subscription invoices. Streamed and chunked. Cells starting with `= + - @` are prefixed with `'`, so spreadsheets never run them as formulas |
| Logs | `/admin/logs` | audit log, filterable by area prefix, user email, business and date |

## Tables

- `cms_pages`: slug unique, title, meta_description, body (Markdown), is_published, in_footer, updated_by.
- `platform_settings`: key → value, read through one cached map (`Setting::get`); the cache is cleared on save.
