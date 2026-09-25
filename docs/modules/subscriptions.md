# subscriptions

STATUS: COMPLETE — Phase 27 (SaaS Billing). Verified 2026-09-26: full Pest suite green (259 tests / 1602 assertions).

Code: `App\Modules\Billing` (core: every business has a Billing page).

## Model

| Concept | Where it lives |
|---|---|
| Plans / module pricing | `module_plans`: a **Monthly** and a **Yearly** row per module (yearly = 10 × monthly), plus `limits` |
| Per-business price override | `tenant_modules.price_cents` (monthly; yearly = × 10), set by a Super Admin |
| Entitlement (can they use it) | `tenant_modules` (status, trial_ends_at, expires_at). `ModuleService::isEnabled` requires `expires_at` null |
| Subscription | `subscriptions`: one per business, interval, current period, coupon, cancel-at-period-end |
| Subscription items | `subscription_items`: the modules paid for |
| Invoices | `billing_invoices` + `billing_invoice_items`: idempotent by `source_key` |
| Coupons | `billing_coupons`: % or ₱ off, once / repeating (n invoices) / forever, max redemptions, expiry |
| Usage & limits | `Billing\Support\Usage`: live counts of properties, rooms, restaurants and team members against the limit |

A business buys modules individually. For example, Property ₱1,499 + Booking ₱1,999 + Restaurant ₱1,499 + CRM ₱899 … per month (seeded prices in `ModuleSeeder::moduleCatalog()`). Non-core dependencies are added automatically: Booking brings Property, and POS brings Restaurant.

## Rules

- **Trials**: a module still in its trial is charged only from the trial end, prorated over the period. Re-subscribing never starts a new trial.
- **Mid-period add**: a prorated invoice is issued for the rest of the period.
- **Removal**: takes effect immediately, with no credit. It is refused while another active module depends on the one being removed, or when it is the last module.
- **Interval switch**: applies from the next renewal.
- **Cancel**: the subscription ends at the close of the period, and its modules are suspended then.
- **Coupons**:
  - Redemption uses a conditional increment, so the last redemption cannot be won twice.
  - One coupon per subscription.
  - A coupon discounts invoices until its cycles are used up.
- **Unpaid invoices**:
  - Invoices are due 7 days after they are issued (`DUE_DAYS`). When the due date passes, the subscription becomes `past_due` and owners get a `billing_past_due` notification.
  - After another 7 days (`GRACE_DAYS`), the subscribed modules are suspended (`tenant_modules.expires_at`).
  - Paying restores the modules immediately, once no other overdue invoice remains.
- **Trial expiry**:
  - A module whose trial has ended and that is on no live subscription is expired by `billing:run`.
  - A Super Admin grant without a trial (`trial_ends_at` null) never expires.
- **Zero-total invoices** are recorded as paid (`payment_method = none`).
- **Limits**:
  - The limit comes from `tenant_modules.limits[metric]` (override), else the module's Monthly plan `limits`; no value means unlimited.
  - Seeded limits: staff 25 (core), properties 5 and rooms 150 (property), restaurants 3 (restaurant).
  - Limits are enforced when a property, room or restaurant is created and when a team member is added. The check runs before validation.

## Payment

- **PayMongo checkout** (`billing.invoices.pay`): this is separate from guest `payments`, so no wallet or commission side effects occur. The return URL and the `checkout_session.payment.paid` webhook both re-read the session and record the payment only when it is paid for the exact invoice total. The webhook falls back to invoices when no guest payment matches.
- **Bank transfer**: a Super Admin marks the invoice paid with a reference (`/admin/billing`).

## Screens

- `/dashboard/billing` (`billing.view`; `billing.manage` to change anything; both owner-only): subscription, modules (subscribe / add / remove), interval, coupon, cancel / keep, usage, invoices. Invoice page with print and pay.
- `/admin/billing` (Super Admin): MRR, invoices (mark paid, void), subscriptions, coupons (create, enable / disable).

## Commands

- `php artisan billing:run` (daily): renew periods (catching up if missed, never double-billing), end cancelled subscriptions, flag overdue invoices, suspend after grace, expire unpaid trials.

## Open items

- No proration credit on removal or on an interval change.
- No tax line: prices are VAT-inclusive.
- Accounting does not post the platform's own SaaS revenue (the platform is not a tenant).
