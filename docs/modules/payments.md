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

## Transaction history

Guest: `/account/payments` (own payments across businesses via `Payment::forCustomer`) plus a payment panel on each trip. Host: a payment panel on the booking page (view composer; Booking and Customer modules do not depend on Payments).

## Tests

`tests/Feature/PaymentsTest.php` (12): checkout amount/auth/reuse, disabled mode, bad and stale signatures, paid → confirmed exactly once (replay and re-send), unpaid session despite webhook body, amount mismatch, return-URL verification, failed + retry, refund success, refund refused (veto), paid after cancel, history isolation.
