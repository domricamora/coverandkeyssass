# API.md

## REST API v1 (Phase 31)

Base URL `/api/v1`, JSON only. The code lives in `app/Modules/Api`: `routes.php`, the controllers, `Support/Present.php` (response shapes) and `Middleware/ResolveApiTenant.php`.

### Authentication

Laravel Sanctum personal access tokens. There is no session and no cookie.

| Call | Notes |
|---|---|
| `POST /auth/token` `{email, password, device_name, read_only?}` | Returns `201 {token, token_type: "Bearer", abilities, expires_at}`. Unknown email and wrong password give the same 422, so the endpoint can't be used to probe for accounts. Suspended users get a 422. Throttled to 6 requests a minute. |
| `GET /auth/me` | The token's user, their active businesses (slug, name, role) and the token's abilities. |
| `DELETE /auth/token` | Revokes the current token and returns 204. |

- Send `Authorization: Bearer <token>`.
- Abilities: tokens get `["read", "write"]`, or `["read"]` if requested with `read_only: true`. Every mutating route requires `write`; a read-only token gets 403 "Invalid ability provided."
- Tokens expire after 30 days (`SANCTUM_EXPIRATION`, in minutes).
- Rate limit: 120 requests a minute per user (or per IP when anonymous). Placing bookings and orders is limited to 20 a minute.

### Conventions

- Lists are paginated in Laravel's format: `data`, `current_page`, `last_page`, `per_page`, `total`, `links`. Pass `?page=` to page through.
- Money is `{amount: "4800.00", currency: "PHP"}`, with the amount as a decimal string.
- Dates are ISO-8601 (`2026-09-26`, `2026-09-26T10:00:00+00:00`).
- Errors use Laravel's JSON shape: `message`, plus `errors` for 422 responses.
- A listing or record the caller can't see returns 404, never 403, so the API doesn't reveal what exists.

### Public catalogue (no token)

These return published listings only, through the same search service and filters as the website: `q`, `location`, `type`, `guests`, `price_min`, `price_max`, `amenities[]`, `cuisines[]`, `price_level`, `sort`.

| Call | Returns |
|---|---|
| `GET /properties` | Paginated property cards |
| `GET /properties/{slug}` | Card plus description, check-in/out times, amenities, photos |
| `GET /properties/{slug}/rooms` | Bookable room types with prices |
| `GET /properties/{slug}/reviews` | Published reviews (reviewer's first name only) |
| `GET /restaurants` | Paginated restaurant cards |
| `GET /restaurants/{slug}` | Card plus description, phone, opening hours, photos |
| `GET /restaurants/{slug}/menu` | Categories, then items, then modifier groups and options, with item and option ids for ordering |

### The customer (`/me`, token required)

| Call | Notes |
|---|---|
| `GET /me/bookings`, `GET /me/bookings/{reference}` | The caller's own stays only. Anyone else's reference returns 404. |
| `GET /me/orders` | Orders with line items |
| `GET /me/payments` | Payments with their booking or order reference |
| `GET /me/notifications`, `POST /me/notifications/{id}/read` | Marking read requires `write`. |
| `GET /me/messages` | Message threads |
| `POST /properties/{slug}/bookings` | Requires `write`. Body: `{check_in, check_out, room_type_id, quantity, adults, children?, guest_phone?, special_requests?, promo_code?}`. Same rules as the website's "Request to book"; creates a `pending` marketplace booking for the host to confirm. |
| `POST /restaurants/{slug}/orders` | Requires `write`. Body: `{lines: [{item_id, quantity, option_ids?}], fulfillment: pickup\|delivery, payment_method: cash, customer_phone, delivery_address?, delivery_zone_id?, notes?}`. Prices are computed on the server. Online payment stays on the website, because it needs PayMongo's hosted checkout. |

### The business (`/business`, token + `X-Tenant: <business slug>`)

The caller must be an active member of an active business. Otherwise every route returns 404. Each route checks the same permission as the matching dashboard screen, and a missing permission returns 403.

| Call | Permission |
|---|---|
| `GET /business/users` | `team.view` |
| `GET /business/properties` | `properties.view` |
| `GET /business/rooms?property=` | `rooms.view` |
| `GET /business/bookings?status=&from=&to=` | `bookings.view` |
| `POST /business/bookings/{reference}/status` `{status, reason?}` | `bookings.update`, plus `write`. Uses the booking state machine, so check-in date guards, room-night release and folio behaviour all apply. |
| `GET /business/restaurants` | `restaurants.view` |
| `GET /business/orders?status=` | `orders.view` |
| `POST /business/orders/{reference}/status` `{status, reason?}` | `orders.manage`, plus `write`. Uses the order state machine. |
| `GET /business/delivery/zones` | `delivery.manage` |
| `GET /business/payments` | `wallet.view` |
| `GET /business/customers?q=` | `crm.view` |
| `GET /business/reviews` | `reviews.view` |
| `GET /business/subscription` | `billing.view` |

### Example

```bash
TOKEN=$(curl -s -X POST https://host/api/v1/auth/token \
  -d email=owner@aplaya.example.test -d password=password -d device_name=cli | jq -r .token)
curl -s https://host/api/v1/business/bookings?status=checked_in \
  -H "Authorization: Bearer $TOKEN" -H "X-Tenant: aplaya-beach-resort" -H "Accept: application/json"
```

## Web routes (Phase 01)

Guest:
- `GET /` landing · `GET|POST /login` · `GET|POST /register` · `POST /logout`
- password reset + email verification routes (Breeze)

Tenant area (`auth`):
- `GET /tenants` business selector · `GET|POST /tenants/create|store`
- `POST /tenants/{tenant}/switch` — enter business context (membership + active status enforced)

Business context (`auth` + `tenant.context`):
- `GET /dashboard` overview
- `GET /dashboard/team` — Livewire full-page component (owner/manager only, policy-checked)
- `GET|PATCH /dashboard/settings` — rename business (policy: tenants.update)

Super Admin area (`auth` + `super.admin`):
- `GET /admin` platform overview
- `GET /admin/users` · `POST /admin/users/{user}/suspend` · `POST /admin/users/{user}/activate`
- `GET /admin/tenants` · `GET|POST /admin/tenants/create|store` · `GET /admin/tenants/{tenant}`
- `POST /admin/tenants/{tenant}/suspend|activate` · `DELETE /admin/tenants/{tenant}`

CLI: `php artisan superadmin:create {name} {email} {--password=}`.
