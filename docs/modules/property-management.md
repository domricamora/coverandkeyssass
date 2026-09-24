# property-management

STATUS: COMPLETE — Phase 04 (Host property management). Verified 2026-09-17: full Pest suite green (98 tests / 320 assertions, MySQL `hospitality_os_testing`).

## Purpose

The host-side management surface for the tenant-owned listings the
Marketplace publishes (Phase 03). Covers the master plan §PHASE 04 feature
list: property profile, photos, videos, amenities, policies, location,
rooms, room types, room inventory, rates, availability and property staff.
Extends the existing Marketplace `Property` — no duplicate system.

## Models (`App\Modules\PropertyManagement\Models`)

| Model | Table | Notes |
|---|---|---|
| `RoomType` | `room_types` | Sellable category per property (occupancy, bed configuration, base/weekend price, min stay). Unique name per property among live rows (app-enforced). |
| `Room` | `rooms` | Physical inventory ("101, 102…") behind a room type. Status `active / maintenance / inactive` controls sellability. Room number unique per property among live rows. |
| `RatePeriod` | `rate_periods` | Date-range price overrides per room type (inclusive range). Overlapping ranges for a room type are rejected. |
| `AvailabilityBlock` | `availability_blocks` | Takes a room type — or one room inside it — off the market for an inclusive date range (maintenance / owner block / event). |
| `PropertyStaff` | `property_staff` | Per-property assignment of a business member (manager, front desk, housekeeping, maintenance, staff), unique per property + user. |

All are tenant-owned through `BelongsToTenant` (deny-by-default).

## Services

| Service | Responsibility |
|---|---|
| `AvailabilityService` | Single source of truth for sellable inventory: `blockedRoomIds(property, from, to)` and `availableRoomCount(roomType, from, to)`. A room is unsellable when any block intersects the window — a block on the room itself or covering its whole room type. The booking engine (Phase 05) builds on these primitives. |

## Routes & controllers (host side)

Loaded with `auth + tenant.context + module.active:property`, prefixed
`/dashboard/properties`, names `properties.*`:

| Area | Routes |
|---|---|
| Property CRUD | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `publish`, `unpublish` |
| Media | `media.store` (photo/video URL), `media.cover`, `media.destroy` |
| Inventory workbench | `inventory` (room types + rooms + rates + blocks on one screen) |
| Room types | `room-types.store / update / destroy` (delete blocked while rooms are assigned) |
| Rooms | `rooms.store / update / destroy` per room type |
| Rates | `rates.store / destroy` per room type |
| Availability | `availability.store / destroy` |
| Staff | `staff.index / store / update / destroy` |

### Route binding caveat

`{property}` and nested parameters are deliberately NOT bound to models:
Laravel's `SubstituteBindings` runs **before** `tenant.context`, so implicit
binding would resolve rows without the tenant scope. Controllers resolve
every parameter manually — `resolveProperty()` (scoped slug lookup + tenant
assert) and through tenant-scoped parent relations
(`$property->roomTypes()->findOrFail(...)`). Foreign rows are a plain 404.

## Authorization

| Permission | Who | Controls |
|---|---|---|
| `properties.view/create/update/delete/publish` | owner all; manager no delete; front_desk view; staff none | property profile + lifecycle (via `PropertyPolicy`, Phase 03) |
| `rooms.view/create/update/delete` | owner all; manager no delete; front_desk view | room types + rooms |
| `rates.view` / `rates.manage` | owner + manager manage; front_desk view | rate periods |
| `availability.view` / `availability.manage` | owner + manager manage; front_desk view | availability blocks |
| `properties.staff.manage` | owner + manager | property staff assignments |

Every action is also audited (`property.created/updated/deleted/published/
unpublished`, `property.staff.*` through `AuditLogger`).

## Media

The polymorphic `media` table gained a `kind` column (`image` / `video`).
`HasMedia::galleryUrls()` exposes only images; `videos()` / `videoUrls()`
list promo videos. The first photo added becomes the cover automatically;
"Make cover" promotes any photo.

## Views

Namespaced `property-management::`: `properties/index`, `create`, `edit`
(profile + policies + amenities + photos + videos), `show` (overview),
`inventory` (room types, rooms, rates, blocks), `staff`. Built on
`<x-app-layout>` and the bnb design system — no JS build changes.

## Tests

`tests/Feature/PropertyManagementTest.php` +
`tests/Support/PropertyManagementFixtures.php` (module-aware business
fixtures on top of `MarketplaceFixtures`):

- authentication + business-selection bounce,
- module gating (403 while `property` module disabled),
- tenant isolation (foreign properties/media 404 even with permissions),
- per-role permissions (owner / manager / front_desk / staff),
- property CRUD + validation + audit trail + publish lifecycle (incl. marketplace visibility),
- media: photos vs videos, cover promotion, removal, cross-property 404,
- room types + rooms: uniqueness, inventory counting, guarded deletes,
- rate periods: overlap rejection, boundary ranges, deletion,
- availability: service semantics + HTTP blocks + reopening,
- staff: assignment (members only), re-assignment, role change, removal.
