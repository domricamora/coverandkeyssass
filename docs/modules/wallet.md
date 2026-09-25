# wallet

STATUS: COMPLETE — Phase 08 (Host wallet and commissions). Verified 2026-09-24: full Pest suite green (146 tests / 560 assertions).

## Money flow

```text
Booking ₱12,500 paid online (PaymentPaid)
→ commission at the resolved rate (default 10%): platform ₱1,250, host ₱11,250
→ host wallet PENDING +11,250
→ guest checks out (or no-show)          → PENDING −11,250, AVAILABLE +11,250
→ payment refunded (PaymentRefunded)      → host amount taken back from whichever bucket holds it
→ host requests payout                    → AVAILABLE −amount (payout "requested")
→ Super Admin transfers + marks paid       (or rejects → AVAILABLE +amount)
```

Earnings stay pending until the stay is over, so money for a stay that may still be refunded is never withdrawn. After a refund of released earnings, the available balance can go negative; later earnings offset it.

**Food orders (Phase 11):** an online-paid order earns a commission at the restaurant's rate (`commissions.order_id`). It is released when the order is **completed** and reversed on refund. Cash orders earn no commission.

## Tables / models (`App\Modules\Wallet\Models`)

| Model | Table | Notes |
|---|---|---|
| `CommissionRate` | `commission_rates` | Platform-level. `global`, `listing` (morph → property / restaurant), `promotional` (date window, optional listing). |
| `Commission` | `commissions` | One per paid payment (unique `payment_id`): gross, rate, platform fee, host amount; `pending → released / reversed`. |
| `Wallet` | `wallets` | One per business (unique `tenant_id`): `pending_balance`, `available_balance`. |
| `WalletTransaction` | `wallet_transactions` | Append-only ledger (earning, release, reversal, payout, payout_reversal), signed amount per bucket plus `balance_after`. Per bucket, the ledger sum equals the balance (asserted in tests). |
| `Payout` | `payouts` | Host withdrawal: amount, method (bank / gcash / maya), account; `requested → paid / rejected`, admin reference / note. |

## Rate resolution (first match)

Promotional rate for the listing → promotional rate for all listings → listing rate → global rate → `COMMISSION_DEFAULT_RATE` (10). Resolved on the payment date. Restaurant rates can be set now and apply once restaurant payments arrive (Phase 09+).

## Integrity

- Every balance change runs in a transaction holding a `FOR UPDATE` lock on the wallet row.
- The earning is recorded inside the same transaction that marks the payment paid and confirms the booking. If any step fails, all of it rolls back and the PayMongo webhook retry redoes it (tested). There is no "paid but never credited" state.
- One commission per payment (unique index plus a check under lock); payout settlement locks the payout row, so double settlement is refused.
- Payout request: minimum ₱100, at most the available balance.

## Routes / permissions

Host `/dashboard/wallet` (`auth`, `tenant.context`): `wallet.view` (owner, manager) shows balances, ledger, commissions and payouts; `payouts.request` (owner) opens the payout form.
Super Admin: `/admin/commissions` (global / listing / promotional rates plus platform revenue totals), `/admin/payouts` (queue; mark paid with a transfer reference, or reject). Everything is audited (`commission_rate.*`, `payout.*`).

Payouts are settled manually: PayMongo payouts are not used. Add an automated transfer provider here when one is chosen.

## Tests

`tests/Feature/WalletTest.php` (10): split at default rate, idempotent earning, rate priority, admin rate screens, release on check-out, reversal before the stay, reversal after a no-show release, payouts (limits, paid / rejected, no double settlement), permissions, rollback when crediting fails with webhook retry.
