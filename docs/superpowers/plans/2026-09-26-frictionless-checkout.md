# Frictionless Checkout Plan (guest checkout + PayPal)

**Goal:** Book stays, order food and book tables without a registration step (Booking.com / Agoda style), and pay now through PayMongo or PayPal (sandbox first), or pay at the property.

**Identity rule:** the checkout collects name, email and phone.
- New email: create the account quietly (random password, unverified) and sign the guest in. The guest can set a password later through "forgot password".
- Email that already has an account: never sign in on an email alone (that would be an account takeover). Ask for the password inline, or email a one-time sign-in link (`URL::temporarySignedRoute`, 30 min) that returns to the same step.
- The existing auth-only endpoints (reserve, cart checkout, table reserve, pay) keep their contracts and tests. The guest step runs just before them.

## Tasks

- [x] 1. `POST /guest/identify` (JSON, throttled): `{name, email, phone}` → `{status: 'signed_in'}` (new account, now signed in) or `{status: 'existing'}`. `POST /guest/password` (JSON): email + password → sign in. `POST /login-link` (JSON): email + `to` → mails a signed link. `GET /login-link/{user}` (signed) → signs in, redirects to `to` (same-site path only). Tests.
- [x] 2. React `GuestDetails` (widgets/ui): name, email, phone, then "continue" handles both paths (password field or "email me a link"). Used by the stay review page, checkout and table booking.
- [x] 3. The stay review page and `/cart` checkout open signed out. The stay panel and table booking skip `/continue`.
- [x] 4. PayPal gateway (`services.paypal`: client_id, secret, mode) using Orders v2: create (intent CAPTURE, PHP) → approve URL; return → capture and verify the amount → `markPaid`. `Payment.provider` = `paypal`. `PaymentService::checkout(…, provider)`. Tests with `Http::fake`.
- [x] 5. Review step: payment choice (pay at property / PayMongo / PayPal, only providers with keys). Reserve with `pay=paymongo|paypal` goes straight to the provider. The order checkout gets PayPal too.
- [x] 6. QA 390/1440, full suite, docs (`docs/modules/payments.md` sandbox steps), changelog, session state.
