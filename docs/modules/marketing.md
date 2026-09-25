# marketing

STATUS: COMPLETE — Phase 22 (Marketing). Verified 2026-09-26: full Pest suite green (231 tests / 1336 assertions). `php artisan marketing:run` was also run against the dev data.

Code: `App\Modules\Marketing`. It is part of the **crm** catalogue module ("Loyalty & campaigns", `module.active:crm`) and builds on CRM (Phase 21) contacts, segments, consent and communication history.

## Campaigns

- Email or SMS to an audience: **everyone opted in**, a **CRM segment** (VIP, Frequent Guest, Inactive, …) or a **tag**. Only contacts with `marketing_consent` and an address for the channel are reached.
- Placeholders: `{name}` (first name), `{business}`, `{link}`, `{coupon}`.
- Optional **personal coupons**: each recipient gets a single-use code from a chosen promotion (`WELCOME-AB12CD`).
- Workflow: draft → send now, or schedule (`marketing:run` sends campaigns that are due) → sent.
- **Resumable and never doubled**: `marketing_recipients` is unique per campaign × contact, each message is logged in the CRM history under `campaign:{id}:contact:{id}`, and a sent campaign cannot be sent again.
- Email: `MarketingMessage` (HTML + text) with a **signed one-click unsubscribe** link (`/unsubscribe/{tenant}/{contact}`) that clears consent.
- SMS: `Support\SmsSender`, drivers `log` (default) and `array` (tests). "Reply STOP to opt out" is appended. **Add a carrier driver before sending real SMS.**

## Promotions, coupons, discount codes

- **Discount codes** are shared `promotions` (stays: booking screen; orders: restaurant order screen).
- **Coupons** (`coupons`) are personal, single-use codes that redeem their promotion. `Promotion::lookup()` resolves either kind for bookings and orders. `Promotion::redeem()` counts the use and spends the coupon with a **conditional update**, so two checkouts racing on one coupon cannot both win (the loser rolls back).
- The marketing screen lists every promotion with its uses and coupons issued / redeemed.

## Automations (`marketing:run`, per business, off until enabled)

| Automation | Trigger | Kind |
|---|---|---|
| Abandoned booking | marketplace booking still pending / held after the delay | service (no consent needed) |
| Abandoned cart | a signed-in guest's saved cart untouched for the delay, with no order at that restaurant since | marketing (consent) |
| Review request | stay checked out, delay passed | service |
| Post-stay offer | stay checked out, delay passed (default 7 days), optional personal coupon | marketing |
| Customer reactivation | contact in the CRM "Inactive" segment, optional coupon, once per inactive spell | marketing |

Each has a stable source key (`auto:{type}:{id}`), so each guest gets each follow-up once. Carts come from Ordering's `CartChanged` event (`saved_carts`: kept on add, deleted at checkout). Service messages carry no unsubscribe link.

## Screens / permissions

- `/dashboard/marketing`: campaigns, new campaign, automations (on / off, delay, subject, message, coupon), promotions and coupons.
- `/marketing/campaigns/{id}`: preview, reachable audience count, recipients with coupons, send / schedule.
- Permissions `marketing.view`, `marketing.manage` (owner, manager).

Schedule **`php artisan marketing:run`** (e.g. every 15 minutes) and `accounting:sync` (daily) in production.

## Tests

`tests/Feature/MarketingTest.php` (7 tests): opt-in only, personal coupon and unsubscribe link, CRM log, no second send; tag audience and SMS to the VIP segment; coupon redeemed once on an order (case-insensitive code); signed unsubscribe (tampered link refused); five automations run once each with consent rules; saved cart reminder only after consent, cart forgotten at checkout; screens, scheduling, automation settings, front desk refused, module gating.
