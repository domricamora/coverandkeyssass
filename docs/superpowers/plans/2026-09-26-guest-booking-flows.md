# Guest Booking Flows Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild stay booking, restaurant ordering and table booking for guests as React widgets and pages with a single, reload-free flow.

**Architecture:** Listing pages stay Blade; `resources/js/widgets.jsx` mounts React components into `[data-widget]` elements with server-written JSON props. Checkout-style steps are Inertia pages in a light public shell. New JSON endpoints wrap the existing services; business rules do not move.

**Tech Stack:** Laravel 11 + Pest, Inertia v3 + React 19, Tailwind 3 (utilities from `app.css`), Vite.

**Spec:** `docs/superpowers/specs/2026-09-26-guest-booking-flows-design.md`

## Global Constraints

- Run PHP via `cmd.exe //c "_ai\run.bat php artisan …"`; build with `node node_modules/vite/bin/vite.js build`.
- Widgets style with Tailwind utilities only (tokens `brand`, `fg`, `line`, `coral`, `soft`…); never the class names `.btn`, `.btn-primary`, `.field`, `.card` (they collide with public CSS).
- Accounts stay required to submit; selection survives sign-in via `/continue?to=` (relative path only).
- Existing endpoints (`marketplace.properties.reserve`, `cart.checkout`, `marketplace.restaurants.reserve`, `marketplace.restaurants.slots`) keep their contracts; their tests must stay green.
- Design: Lagoon & Coral, square corners, Instrument Sans + Geist; 390 px and 1440 px must work.

---

### Task 1: Widget mounting + sign-in return

**Files:**
- Create: `resources/js/widgets.jsx`, `resources/js/widgets/ui.jsx`
- Modify: `vite.config.js` (input), `resources/views/layouts/public.blade.php` (load widgets entry when a page pushes `widgets`), `routes/web.php` (`/continue`), `RegisteredUserController` (intended redirect)
- Test: `tests/Feature/GuestFlowTest.php`

**Interfaces:**
- Produces: scan of `[data-widget]` → `REGISTRY[name](props)`; `route('continue', ['to' => '/path'])`; `widgets/ui.jsx` exports `BTN`, `BTN_GHOST`, `FIELD`, `LABEL`, `money(amount, currency)`, `csrf()`, `json(url, {method, body})`.

- [ ] Test: signed-out `GET /continue?to=/cart` redirects to login; after login the intended redirect reaches `/cart`; `to=https://evil.test` or `//evil` falls back to `/`.
- [ ] Route: `Route::get('/continue', fn (Request $r) => redirect(preg_match('#^/(?!/)#', (string) $r->query('to')) ? $r->query('to') : '/'))->middleware('auth')->name('continue');`
- [ ] `RegisteredUserController` → `redirect()->intended(...)` so sign-up also returns.
- [ ] `widgets.jsx`: `document.querySelectorAll('[data-widget]').forEach(el => createRoot(el).render(<C {...JSON.parse(el.dataset.props)} />))`.
- [ ] Vite input + public layout `@stack('widgets')`; pages `@push('widgets') @vite('resources/js/widgets.jsx') @endpush`.
- [ ] Run tests, commit.

### Task 2: Stay quote endpoint

**Files:**
- Create: `app/Modules/Marketplace/Controllers/StayQuoteController.php`
- Modify: `BookingService::promotionFor` → public; `app/Modules/Marketplace/routes.php`
- Test: `tests/Feature/GuestFlowTest.php`

**Interfaces:**
- Produces: `GET /property/{slug}/quote?check_in&check_out&guests&promo_code` (name `marketplace.properties.quote`) → `{nights, currency, options: [{room_type_id, name, sleeps, free, per_night, total}], promo: {code, discount}|null, promo_error, free_cancel_until}`.

- [ ] Test: property with 2 free rooms → option `free: 2`, total = nights × rate; bad dates → 422; valid promo → `promo.discount > 0`; unknown code → `promo_error`.
- [ ] Implement inside `TenantContext::runAs($property)`: per active room type with `max_guests >= guests`, `free = count(availability->freeRoomIds(type, in, out-1))`, `bookings->quote(type, in, out)` (skip on ValidationException); promo via `bookings->promotionFor(...)` with ValidationException → `promo_error`.
- [ ] Run tests, commit.

### Task 3: StayPanel widget on the property page

**Files:**
- Create: `resources/js/widgets/StayPanel.jsx`
- Modify: `app/Modules/Marketplace/Views/properties/show.blade.php` (the GET dates form + POST reserve form → one `data-widget="StayPanel"`), `app/Modules/Marketplace/Controllers/PropertyController.php` (props)
- Test: page contains `data-widget="StayPanel"` with room types + quote URL

**Interfaces:**
- Consumes: Task 2 endpoint, `stay.review` (Task 4), `/continue`.
- Props: `{quoteUrl, reviewUrl, signedIn, currency, dates: {check_in, check_out, guests}, roomTypes: [{id, name, sleeps}]}`.

- [ ] Panel: dates, guests stepper, room type (cheapest default), rooms; debounced quote fetch; per night, total, "Only N left", free-cancellation line, inline errors; "Reserve" → review URL (signed in) or `/continue?to=<review>`.
- [ ] SEO blocks untouched; update date-search test assertions if they read removed form text; commit.

### Task 4: Stay review & confirm page

**Files:**
- Create: `app/Modules/Booking/Controllers/StayReviewController.php`, `resources/js/Pages/Stay/Review.jsx`, `resources/js/react/PublicShell.jsx`
- Modify: `app/Modules/Booking/routes.php` (`GET /stay/{property}/review`, auth, `stay.review`)
- Test: `tests/Feature/GuestFlowTest.php`

**Interfaces:**
- Props: `{property: {name, slug, cover, where}, stay: {check_in, check_out, nights, guests, room_type_id, quantity}, option: {name, per_night, total, currency}, guest: {name, email}, quoteUrl, reserveUrl, backUrl}`.

- [ ] Test: guest → login redirect; signed in → Inertia `Stay/Review` with `option.total`; no free rooms → back to property with error.
- [ ] Page: summary, guest details (phone, requests), promo "Apply" (re-quote), total, "Request booking" posts existing reserve endpoint → `account.bookings.show`.
- [ ] Commit.

### Task 5: Cart JSON API

**Files:** `CartController` (`add`/`update` JSON when `wantsJson()`, new `summary()`), `app/Modules/Ordering/routes.php` (`GET /cart/summary`); test in `GuestFlowTest`.

**Interfaces:** summary JSON `{restaurant: {name, slug}|null, lines: [{key, name, mods, qty, total}], count, subtotal, currency, replaced, error}`.

- [ ] Test: JSON add → count 1; other restaurant → `replaced: true`; qty 0 removes line. Implement; commit.

### Task 6: MenuOrder widget

**Files:** `resources/js/widgets/MenuOrder.jsx`; restaurant show Blade (menu → mount); `Marketplace/Controllers/RestaurantController.php` (props: menu tree, ordering flag, cart summary, urls).

- [ ] Category chips, item sheet with modifier rules, qty, notes, "Add · ₱total"; cart bar + drawer (+/−), "Checkout" → `/cart`; read-only menu when ordering is off. Commit.

### Task 7: Checkout page

**Files:** `resources/js/Pages/Order/Checkout.jsx`; `CartController::show` → `Inertia::render('Order/Checkout', …)`; cart-page assertions in ordering/room service/delivery tests → `assertInertia`.

- [ ] Lines with qty edit; fulfillment tabs (pickup / delivery zone+address / room service stay); ASAP or scheduled; phone, notes, promo re-quote; payment method; totals; "Place order" → `cart.checkout`; signed out → `/continue?to=/cart`. Commit.

### Task 8: TableBooking widget

**Files:** `resources/js/widgets/TableBooking.jsx`; restaurant show Blade reservation form → mount; props `{slotsUrl, reserveUrl, signedIn, prefill: {date, time, party}}`.

- [ ] 14-day date strip, party stepper, slot grid, phone + requests; "Book table" form-posts reserve endpoint (redirects to reservations); signed out → `/continue?to=/restaurant/{slug}?date=&time=&party=#book` with prefill. Commit.

### Task 9: QA, docs, full suite

- [ ] Screenshots 390/1440: property panel, review, restaurant menu + table, checkout; fix issues.
- [ ] Full suite green; CHANGELOG + SESSION_STATE; commit.
