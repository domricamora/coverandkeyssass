# PERFORMANCE.md

## Phase 33 — Performance pass (2026-09-26)

### Method

We measured before changing anything. `PERF_TRACE=true` in `.env` (dev only, and ignored in production) logs each request's query count and DB time, plus every lazy-loaded relation (N+1 suspect), to `storage/logs/perf.log`. A headless-Chrome crawl of every public page and every dashboard sidebar screen, run as the demo owner, produced the numbers below.

### Results (queries per page, demo data)

| Page | Before | After | Change |
|---|---|---|---|
| Dashboard overview | 65 | 20 | Permission set resolved once per request |
| Bookings | 70 | 16 | ″ |
| Housekeeping | 79 | 19 | ″ |
| Staff | 76 | 22 | ″ |
| Inventory | 79 | 19 | ″ |
| Billing | 117 | 66 | ″ |
| Guests (CRM) | 170 | 116 on first view, then only changed rows | Incremental sync |
| Accounting | 289 | 187 | Posted-key set per sync; view-time folio sync scoped to live and recent stays |

The crawl logged no lazy-loading (N+1) violations.

### Changes

- **Permissions**
  - `User::hasPermissionTo()` used to cost three queries per call: the platform-admin check, a cache read (the cache store is the database) and a role lookup. The sidebar makes about 20 calls per page.
  - It now makes one query per business per request, loading the user's full permission-name set, memoised on the user instance. `isPlatformAdmin()` is memoised too.
  - Role changes on the same instance clear the memo.
- **Accounting**
  - `LedgerService` loads the business's posted source keys once per sync. `has()` and the idempotent `post()` no longer query per source row. The in-transaction duplicate check stays as the safety net.
  - Report screens sync on view with `sync()`, which re-derives only folios that can still change: open stays, stays touched in the last 60 days, and stays whose payment (late refund) or room-charge order moved.
  - `php artisan accounting:sync` runs `sync(full: true)` nightly as the backstop.
- **CRM**
  - `CrmService::sync()` is incremental, using a per-business cache watermark. It folds only bookings, orders and reservations changed since the last sync, and refreshes only the contacts they touched.
  - With no watermark (first run, or cache cleared), or with `$full`, it does a complete pass, so losing the cache costs time, not data.
- **Indexes** (migration `2026_09_26_120000_add_performance_indexes`)
  - `(tenant_id, updated_at)` on bookings, orders, payments and table_reservations, for the incremental syncs.
  - `(tenant_id, check_in)` on bookings, for the desk list.
  - The migration is reversible and idempotent. MySQL silently drops a foreign key's own index once a composite index can serve it, so `down()` restores a plain `tenant_id` index first.
- **Latent bugs fixed along the way**
  - `Order` had no `user` relation, so CRM never captured an ordering customer's email.

### Already in place (verified, unchanged)

- **Pagination:** every list is paginated (20 to 100 per page), including all API lists. No screen loads a whole table.
- **Eager loading:** listing search and cards eager-load `location`, `propertyType`/`cuisines` and `media`.
- **Indexes:** tenant plus status or date composites on bookings, orders, housekeeping tasks, maintenance tickets, journal entries, CRM contacts and listings, and unique idempotency keys (room nights, folio, journal, payments).
- **Images:** demo photos are at most 1600 px, about 250 KB. Listing images use `loading="lazy" decoding="async"`. The hero video is 720p, 4 MB, and loads only on wide screens without reduced motion; phones get the 120 KB poster.
- **CSS and JS:** built and minified by Vite (about 40 KB of CSS gzipped). One Alpine bundle.

### Deferred, with reasons

- **Queued notifications.**
  - Mail, SMS and push are sent inside the request today. Queuing them needs a tenant-aware job payload: record the tenant id at dispatch and restore `TenantContext` before the job unserialises.
  - Without that, the worker's deny-by-default scope can't re-fetch the `Booking` or `Order` the notification carries, and the job fails.
  - Planned alongside the queue-worker setup in Phase 35. The in-app channel should stay synchronous (`viaConnections(['database' => 'sync'])`).
- **Redis.** Config is ready. Switch `CACHE_STORE`, `SESSION_DRIVER` and `QUEUE_CONNECTION` to `redis` in production (Phase 35). Locally, the database drivers stay.
- **Responsive image variants** (`srcset`). Worth adding when host uploads grow. The demo photos are already capped.
