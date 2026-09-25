# loyalty

STATUS: COMPLETE — Phase 23 (Loyalty). Verified 2026-09-26: full Pest suite green (237 tests / 1381 assertions).

Code: `App\Modules\Loyalty`, part of the **crm** module ("Customer profiles, communication, and loyalty"). Members are CRM contacts (Phase 21).

## Points

- Programme per business (`loyalty_programs`, **off until enabled**): **₱100 spending = 1 point** (configurable), referral bonus (default 200 points each).
- **Earn**: a stay **checked out** or a food order **completed** gives `floor(total ÷ ₱ per point)`. The `BookingTransitioned` / `OrderTransitioned` listeners award it immediately, and `sync()` on the loyalty screen catches up anything missed. Idempotent by source key (`booking:{id}`, `order:{id}`). Anonymous walk-ins earn nothing.
- **Reversal** when that stay or order is refunded.
- **Ledger** (`loyalty_transactions`): append-only, signed points with the balance after, under a row lock on the account. Redemptions and adjustments cannot go below zero.
- **Tiers** follow **lifetime** points (earn + referral − reversals; manual adjustments don't count): Bronze 0, Silver 500, Gold 2,000, Platinum 5,000, each with a perk label. The member page shows the points needed for the next tier.
- **Referrals**: every member has a code. A new member enters a friend's code **before their first earning**. On that first earning both members receive the bonus once (`referral:{id}:referee|referrer`). Self-codes, a second code and late codes are refused.

## Rewards

`loyalty_rewards`: points cost, and either a **coupon** (a personal single-use code from a promotion, the Phase 22 coupons) or **store credit** (₱ amount). Redeeming deducts points and returns the code.

## Gift cards and credits

`gift_cards` handles both: **gift** cards are sold (cash or bank, optional guest, optional expiry), and **credit** cards are a guest's store credit (rewards, goodwill). One balance mechanism:

- `GiftCardService::redeem(code, amount, reference)` locks the card, refuses unknown, void, expired or over-balance use, and writes a redemption line.
- Accepted as a **POS tender** (`gift_card`, code as reference; refunds never go back onto a card) and as a **folio payment** (`gift_card`).
- **Void** records any unspent balance as a `VOID` redemption (breakage).
- **Accounting** (Phase 20): sold card = Dr cash / bank, Cr *Gift cards & store credit* (liability). Credit = Dr *Loyalty rewards & credits* / Cr liability. POS and folio redemptions debit the liability. A void releases the remaining balance to other revenue (sold card) or back against the loyalty expense (credit).

## Screens / permissions

- `/dashboard/loyalty`: programme settings, members by lifetime points (search by name, email or referral code), tier counts, rewards, gift cards (sell / void, outstanding balance).
- `/dashboard/loyalty/members/{id}`: ledger, redeem a reward, adjust points, credit and gift cards.
- Guest `/account/loyalty` (the "Rewards" tab): memberships at every business with points, tier progress, their referral code, a field to enter a friend's code, and credit / gift card balances.
- `loyalty.view` (owner, manager, front desk), `loyalty.manage` (owner, manager).

## Tests

`tests/Feature/LoyaltyTest.php` (6 tests): earning on check-out and order completion, replay-safe, walk-ins skipped, reversal on refund, programme off; tier thresholds with adjustments excluded, overdraw refused; referral rules and the one-time double bonus; credit and coupon rewards, insufficient points; gift card at the POS (partial, bad code) and on the folio (exhausted), void breakage, accounting balances; guest page and host screens with permissions.
