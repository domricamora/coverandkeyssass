# payments

STATUS: COMPLETE — Phase 07 (PayMongo). Verified 2026-09-24: full Pest suite green (136 tests / 485 assertions) with PayMongo faked via `Http::fake()`. **Not yet exercised against the real PayMongo sandbox**: run one test-mode checkout with real `sk_test_…` / `whsk_…` keys before going live.

## Lifecycle

```text
Marketplace booking (pending, rooms held)
→ guest clicks "Pay with PayMongo"  (POST /account/bookings/{ref}/pay)
→ PaymentService::checkout() creates a PayMongo Checkout Session → redirect to checkout_url
→ guest pays (card / GCash / Maya)
→ webhook checkout_session.payment.paid  AND/OR  return URL /account/bookings/{ref}/payment-return
→ PaymentService::sync(): GET /checkout_sessions/{id} from PayMongo, require status "paid" + exact amount + currency
→ markPaid() (row lock, idempotent) → BookingService::transition(confirmed) → customer notified
```

Never confirmed from a browser redirect or from the webhook body: both only *trigger* a server-side lookup at PayMongo.

**Food orders (Phase 11)** use the same pipeline. `payments.order_id` is set instead of `booking_id` (now nullable). `checkoutOrder()` → `/account/orders/{ref}/payment-return` → `markPaid()` sets `orders.payment_status = paid` (it does not transition the order; the kitchen accepts it). Moving an order to `refunded` refunds through PayMongo (`refundOrder()`), and a provider refusal vetoes it. See `ordering.md`.

## Models / tables

| Model | Table | Notes |
|---|---|---|
| `Payment` | `payments` | Tenant-owned. One checkout attempt per row: `checkout_session_id` (unique), `payment_intent_id`, `provider_payment_id` (unique), amount/currency, status `pending / paid / failed / refunded`, method, failure reason, refund id/amount/time. |
| — | `payment_events` | Webhook ledger, `event_id` unique, raw payload, `processed_at`. |

## Idempotency

- Each webhook event id is stored once (`insertOrIgnore`); if the event is already processed, the webhook acknowledges it with 200 and does nothing else. If processing throws, `processed_at` stays null, PayMongo's retry is processed.
- `markPaid()` locks the payment row; a second webhook (new event id), a webhook racing the return URL, or a replay finds it already `paid` and does nothing. So there is never a second payment, confirmation or notification.
- Unique provider ids make a duplicate payment row impossible at the DB level.
- `checkout()` reuses an open pending checkout, so double clicks never open two sessions.

## Other rules

- **Failed payment** (`payment.failed`): payment `failed` with PayMongo's message; booking stays pending; guest can pay again (new session).
- **Refund**: the host moves a booking `cancelled|no_show → refunded`. Payments listens to `BookingTransitioning` and calls `POST /refunds` for the paid payment first. If PayMongo refuses, the transition is vetoed (booking stays cancelled, error shown). Bookings without an online payment refund offline as before.
- **Paid after cancel**: recorded as paid, booking not revived, audited `payment.paid_on_inactive_booking` so the host refunds.
- **Disabled mode**: no `PAYMONGO_SECRET_KEY` → no pay button, `/pay` is 404, and bookings are confirmed by the host (Phase 05 behaviour).

## Webhook security

`POST /webhooks/paymongo` is registered outside the `web` group (no session / CSRF) with `throttle:120,1`. Header `Paymongo-Signature: t=…,te=…,li=…`; HMAC-SHA256 of `"{t}.{raw body}"` with `PAYMONGO_WEBHOOK_SECRET`, compared in constant time; timestamps older than `PAYMONGO_WEBHOOK_TOLERANCE` (300 s) are rejected (replay protection). Invalid → 400.

## Configuration (`config/services.php` → `paymongo`)

`PAYMONGO_SECRET_KEY`, `PAYMONGO_WEBHOOK_SECRET`, `PAYMONGO_PUBLIC_KEY` (unused server-side), optional `PAYMONGO_BASE_URL`, `PAYMONGO_METHODS` (default `card,gcash,paymaya`), `PAYMONGO_WEBHOOK_TOLERANCE`.

Register the webhook once per environment (the response contains the `whsk_…` secret):

```sh
curl -u sk_test_xxx: https://api.paymongo.com/v1/webhooks -H "Content-Type: application/json" \
  -d '{"data":{"attributes":{"url":"https://<host>/webhooks/paymongo","events":["checkout_session.payment.paid","payment.paid","payment.failed"]}}}'
```

## PayPal (Orders v2)

`PayPalGateway` sits next to PayMongo; `PaymentService::providers()` lists the configured ones, and the guest picks one (`provider` on pay / checkout, `pay` on the stay reserve). Flow: create an order (intent CAPTURE, PHP) → PayPal approval page → the return URL (`…/payment-return?token=…&PayerID=…`) calls `sync()`, which **captures server-side** and records the payment only for a COMPLETED capture of the exact amount and currency. PayPal does not move money without our capture call, so no webhook is needed. Refunds go through `/v2/payments/captures/{id}/refund`. Payment rows carry `provider = paypal`, and `provider_payment_id` is the capture id.

Config: `PAYPAL_CLIENT_ID`, `PAYPAL_SECRET`, `PAYPAL_MODE` (`sandbox` → api-m.sandbox.paypal.com, `live` → api-m.paypal.com). Billing invoices still pay through PayMongo only.

## Sandbox run (both providers)

1. **PayMongo:** in dashboard.paymongo.com (test mode), copy `sk_test_…` into `PAYMONGO_SECRET_KEY` and register the webhook with the curl above (you need a public URL, e.g. an ngrok tunnel to `http://localhost/ck/public`). Put the returned `whsk_…` into `PAYMONGO_WEBHOOK_SECRET`. Without the webhook, the return URL still confirms the payment.
2. **PayPal:** at developer.paypal.com, go to Apps & Credentials (Sandbox) and create an app. Put its Client ID and Secret into `PAYPAL_CLIENT_ID` / `PAYPAL_SECRET` with `PAYPAL_MODE=sandbox`. Pay with the generated sandbox *personal* account (Testing Tools → Sandbox Accounts).
3. Run `php artisan config:clear`. Book a stay signed out: on the review step, choose PayMongo or PayPal. The booking turns **confirmed** after the return. Order food with "Pay now" the same way. Then refund one booking from the host side (cancel → refunded).

## Guest checkout (no registration)

The stay review page, food checkout and table booking collect name, email and phone. `POST /guest/identify` creates an account for a new email and signs the guest in. An existing email must use its password (`POST /guest/password`) or a signed 30-minute sign-in link (`POST /login-link` → `GET /login-link/{user}`). See `GuestCheckoutController` and `tests/Feature/GuestCheckoutTest.php`.

## Transaction history

Guest: `/account/payments` (own payments across businesses via `Payment::forCustomer`) plus a payment panel on each trip. Host: a payment panel on the booking page (view composer; Booking and Customer modules do not depend on Payments).

## Tests

`tests/Feature/PaymentsTest.php` (12): checkout amount/auth/reuse, disabled mode, bad and stale signatures, paid → confirmed exactly once (replay and re-send), unpaid session despite webhook body, amount mismatch, return-URL verification, failed + retry, refund success, refund refused (veto), paid after cancel, history isolation.
