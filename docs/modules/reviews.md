# reviews

STATUS: COMPLETE — Phase 24 (Reviews). Verified 2026-09-26: full Pest suite green (241 tests / 1428 assertions). The dev data migrated with every existing review backfilled to its business.

Code: `App\Modules\Reviews`, extending the Marketplace `reviews` table (Phase 03). Reviews are **verified**: a guest reviews what they actually did, once each.

| Review of | When | Listing | Extra |
|---|---|---|---|
| **Booking / Property / Room** | stay checked out or completed (`reviewStay`) | property | `room_type_id` from the booking; categories cleanliness, location, service, value, amenities |
| **Restaurant / Food** | food order completed (`reviewOrder`) | restaurant | categories food, service, value; per-dish stars (`review_item_ratings`, only for dishes in that order) |
| **Restaurant visit** | table reservation seated or completed (`reviewVisit`) | restaurant | categories food, service, value, cleanliness |
| **Host** | — | — | host rating = average of the business's published reviews across its listings |

- One review per booking, order or visit (unique FKs). The old one-per-listing rule is gone, so a returning guest can review each stay.
- Overall `rating` plus nullable `rating_{cleanliness,location,service,value,food,amenities}`; only the categories relevant to that review type are stored.
- `reviews.tenant_id` (backfilled for existing rows) scopes the host screen.
- Verified reviews go **live at once**. The listing's `avg_rating` / `reviews_count` still come from the Marketplace `ReviewObserver`, which now resolves the listing without the tenant scope, so moderation (no tenant context) updates ratings too. This was a bug found by the new tests.

## Replies and moderation

- **Host** (`/dashboard/reviews`, `reviews.view` / `reviews.reply`): filters all / unanswered / 1–2 stars. **Reply publicly** (the first reply notifies the guest via `ReviewReplied`; later edits don't). **Report** a review with a reason (it stays visible until moderated).
- **Super Admin** (`/admin/reviews`, linked from the admin dashboard): reported reviews first. **Keep / publish** clears the report; **Hide** sets `rejected`, removes the review from public pages and ratings, and records the moderator and a note. Audited.

## Public pages

`/property/{slug}` and `/restaurant/{slug}` show a summary above the reviews: overall and count, host rating, category bars, per-room-type ratings (properties), favourite dishes (restaurants). The host's reply is shown under each review. The data comes from a view composer (`ReviewService::summary()`).

## Guest side

- Trip page: the stay review form with category ratings.
- Order page (completed): overall, food, service and value plus stars per dish.
- Table reservations list (seated / completed): a quick visit review.

## Permissions

`reviews.view` (owner, manager, front desk), `reviews.reply` (owner, manager). Moderation is Super Admin only.

## Tests

`tests/Feature/ReviewsTest.php` (4 tests): stay review with categories and room type, once per booking, not by others, a second stay reviewable, listing aggregates; order review with only the ordered dishes rated, not before completion; visit review only once seated; restaurant summary (overall, categories, dishes); host reply with notification, report, public summary with host rating and reply, admin hide → rating recalculated and hidden publicly, non-admin refused; staff / front desk / other-business access; customer-portal form with categories. `CustomerPortalTest` still passes on the new service.
