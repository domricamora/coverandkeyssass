# Guest booking flows in React — design

Date: 2026-09-26 · Status: approved in chat ("go ahead")

## Goal

Fix and rebuild the three guest flows — stay booking, restaurant ordering,
table reservations — as React, without losing the SEO of the public
listing pages.

## Problems today

- Stay panel: a GET "check availability" form and a separate POST reserve
  form that repeats dates; price shows only after a page reload.
- "Sign in to book / order / book a table" drops the guest's selection.
- Ordering: each dish is a full-page POST, then a separate `/cart` page.
- Table booking: small Alpine form, same sign-in wall.

## Decisions

1. **React widgets on server-rendered listing pages.** Property and
   restaurant pages stay Blade (schema, meta, crawlable HTML). Interactive
   parts mount as React islands. Checkout, confirmation and My trips pages
   become Inertia pages.
2. **Accounts stay required**, but sign-in happens at the last step and the
   guest returns to the same step with the selection kept.

## Architecture

- `resources/js/widgets.jsx`: finds `[data-widget]` elements and mounts the
  named component with `JSON.parse(el.dataset.props)`. Built by Vite as its
  own entry; styles scoped so the Blade public CSS is unaffected.
- Widgets: `StayPanel`, `MenuOrder` (menu + cart drawer), `TableBooking`.
- Inertia public shell `resources/js/react/PublicShell.jsx` (logo, account
  menu, footer link back); pages opt in with `layout = PublicShell`.
- JSON endpoints (web middleware, CSRF, throttled):
  - `GET /property/{slug}/quote?check_in&check_out&guests&room_type_id&quantity&promo_code`
    → `StayQuoteService` result per room type (availability, nightly,
    total, cancellation deadline).
  - Cart: existing `cart.add` / `cart.update` accept JSON (`wantsJson`) and
    return the cart summary; new `GET /cart/summary`.
  - Existing `marketplace.restaurants.slots`.
- Business logic stays in `BookingService`, `OrderService`,
  `ReservationService`, `PaymentService`.

## Stay booking flow

1. `StayPanel` on the property page: date range, guests, room type, rooms →
   live quote (per night, total, "only N left", free cancellation until).
2. "Reserve" → `GET /stay/{slug}/review?…` (Inertia `Stay/Review`): summary,
   guest details prefilled, promo code (live discount via quote endpoint
   with `promo_code`), submit → existing reserve endpoint → confirmation.
3. Signed out: review route is `auth`; Laravel's intended URL carries the
   query back after login/register.
4. Confirmation = the existing customer booking page
   (`account.bookings.show`, pay button when PayMongo is on). Converting
   the My trips area to React is a follow-up, not part of this spec.

Sign-in return for all three flows: `GET /continue?to=/relative/path`
(auth). Signed out → login/register stores it as the intended URL; after
sign-in it redirects to `to` (relative paths only).

## Restaurant flow

- `MenuOrder`: categories, items, modifier sheet (min/max rules), quantity,
  notes, add without reload; sticky cart drawer with totals; "Checkout".
- `/cart` becomes Inertia `Order/Checkout`: lines (edit qty/remove),
  fulfillment (pickup / delivery with zone + fee + minimum / room service),
  scheduled time, promo, live totals, place order → customer order page.
- `TableBooking`: date, party size, tappable slot grid, notes → confirm
  inline (signed in) or sign-in with return.

## Testing

- Existing feature tests keep passing (endpoints unchanged).
- New: quote endpoint, cart JSON add/summary, review page props,
  intended-URL return after login.
- Visual check at 390 / 1440; taste + ui-ux-pro-max rules; Lagoon & Coral.

## Out of scope

Guest checkout without an account; map view; SSR.
