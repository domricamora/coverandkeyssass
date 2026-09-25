# accounting

STATUS: COMPLETE — Phase 20 (Accounting). Verified 2026-09-26: full Pest suite green (220 tests / 1243 assertions). `php artisan accounting:sync` was also run against the dev data.

Code: `App\Modules\Accounting`, gated by the **finance** catalogue module (`module.active:finance`). "Accounting should be modular": the books are *derived* from the operational modules, which keep working without it.

## Ledger

- `ledger_accounts`: per-business chart. The defaults (19 accounts) are created on first use and marked with `system_key`: cash, bank, platform wallet, guest receivables, receivables, inventory, input VAT, payables, output VAT, equity, room / F&B / other revenue, COGS, waste, platform commissions, repairs & maintenance, operating supplies, general expenses.
- `journal_entries` + `journal_lines`: double entry. `LedgerService::post()` **refuses unbalanced entries** and is **idempotent by `source_key`** (unique per business). Nothing is edited; corrections are `reverse()` entries.

## Automatic postings (`PostingService::sync()`)

| Source | Entry | Key |
|---|---|---|
| Folio charge (Phase 14) | Dr guest receivables / Cr room (room), F&B (food, room service, minibar) or other revenue. The promo discount reduces revenue. | `folio:{id}` |
| Folio payment / refund | Dr cash (cash), bank (card / transfer / e-wallet) or **platform wallet** (PayMongo) / Cr guest receivables (refunds reverse) | `folio:{id}` |
| Voided folio line | reversal | `folio:{id}:void` |
| Completed food order (not room charge, which is already in the folio) | Dr platform wallet (online) / cash (pay on pickup) / cash and bank per POS payment method; Cr F&B revenue (net), output VAT, other revenue (delivery fee) | `order:{id}` |
| Refunded order | reversal | `order:{id}:refund` |
| Commission (Phase 08) | Dr platform commissions / Cr platform wallet (reversed on refund) | `commission:{id}` |
| Payout paid | Dr bank / Cr platform wallet | `payout:{id}` |
| Stock receipt (Phase 18) | Dr inventory / Cr payables (**supplier**, when it came from a PO) or cash | `stock:{id}` |
| Stock sale / sale return / waste / issue / count | COGS / inventory, waste, supplies, shrinkage ± | `stock:{id}` |
| Maintenance cost (Phase 16) | Dr repairs & maintenance / Cr payables | `maintenance:{id}` |

The platform wallet asset equals money held by the platform: online takings − commissions − payouts. After a refund it goes back to 0 (tested).

Sync runs on every accounting screen view and from `php artisan accounting:sync` (all businesses; schedule it daily).

## Hand-entered books (`BooksService`)

- **Expenses**: gross amount with VAT split out (Dr expense + input VAT), paid from cash, bank or payable.
- **Invoices** (receivables): `INV-00001` numbering, lines, VAT rate. draft → **issued** (Dr receivables / Cr other revenue + output VAT) → payments (partial allowed, never above the balance) → paid. Void only while unpaid (issued ones are reversed).
- **Supplier payments** (payables): Dr payables / Cr cash or bank, at most what is owed to that supplier.

## Reports

Overview (period P&L, VAT, cash / bank / platform wallet / open folios, receivables, overdue invoices, payables, commissions and payouts), journal (filter by account), trial balance (as of a date, with a balanced check), payables by supplier with a pay form, open invoices with aging. VAT payable = output − input for the period.

## Permissions

`accounting.view`, `accounting.manage` (owner, manager).

## Ceilings

- Folio room charges are booked gross (no room VAT split). Add a property VAT setting if rooms must show VAT separately.
- A maintenance cost is booked when the ticket is resolved or closed. Later cost edits are not re-posted.
- Sync re-reads every folio each run. Scope it to recently changed bookings when volumes grow.

## Tests

`tests/Feature/AccountingTest.php` (6 tests): balanced / idempotent / reversal; hotel stay from folio (room + other revenue, cash / bank, void, receivables 0, trial balance balanced); PayMongo platform wallet net of commission and back to 0 on refund; POS sale with output VAT, COGS from recipe stock, refund reversal; purchasing → supplier payables, supplier payment limits, expense with input VAT, invoice lifecycle with partial payments and void, VAT and bank figures; screens, front desk refused, finance module gating, tenant isolation.
