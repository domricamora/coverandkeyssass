<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Booking\Models\Booking;
use App\Modules\Folio\Models\FolioEntry;
use App\Modules\Folio\Services\FolioService;
use App\Modules\Inventory\Models\PurchaseOrder;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Maintenance\Models\MaintenanceTicket;
use App\Modules\Ordering\Models\Order;
use App\Modules\Pos\Models\PosPayment;
use App\Modules\Wallet\Models\Commission;
use App\Modules\Wallet\Models\Payout;

/**
 * Automatic postings (Phase 20): mirrors what the operational modules
 * already record into the ledger. Every source row has a stable
 * source_key, so sync() can run on every report view (and from
 * `accounting:sync`) without ever booking anything twice.
 *
 *   folio charge      Dr guest receivables / Cr room, F&B or other revenue
 *   folio payment     Dr cash | bank | platform wallet / Cr guest receivables (refunds reverse)
 *   food order        Dr cash | bank | platform wallet / Cr F&B revenue + output VAT (+ delivery fee)
 *   commission        Dr platform commissions / Cr platform wallet (reversal on refund)
 *   payout paid       Dr bank / Cr platform wallet
 *   stock receipt     Dr inventory / Cr payables (supplier, via PO) or cash
 *   stock sale/waste  Dr COGS | waste | supplies / Cr inventory
 *   maintenance cost  Dr repairs & maintenance / Cr payables
 *
 * Runs inside the active tenant.
 */
class PostingService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FolioService $folio,
    ) {}

    public function sync(): void
    {
        $this->folios();
        $this->orders();
        $this->wallet();
        $this->stock();
        $this->maintenance();
        $this->giftCards();
    }

    /** Sold cards are prepaid (liability); credits are a loyalty cost; voided balances are released. */
    private function giftCards(): void
    {
        \App\Modules\Loyalty\Models\GiftCard::query()->orderBy('id')->each(function (\App\Modules\Loyalty\Models\GiftCard $card): void {
            $debit = $card->kind === 'gift' ? ($card->sold_via === 'cash' ? 'cash' : 'bank') : 'loyalty_expense';
            $this->ledger->post($card->created_at->toDateString(), ($card->kind === 'gift' ? 'Gift card sold ' : 'Store credit ').$card->code, [[$debit, (float) $card->initial_value, 0], ['gift_card_liability', 0, (float) $card->initial_value]], 'giftcard:'.$card->id, $card->code);

            foreach ($card->redemptions()->where('reference', 'VOID')->get() as $void) {
                $this->ledger->post($void->created_at->toDateString(), 'Voided card '.$card->code, [['gift_card_liability', (float) $void->amount, 0], [$card->kind === 'gift' ? 'other_revenue' : 'loyalty_expense', 0, (float) $void->amount]], 'giftcard:'.$card->id.':void', $card->code);
            }
        });
    }

    private function folios(): void
    {
        // ponytail: re-syncs every folio each run; scope to recently-updated bookings when volumes grow.
        Booking::query()->whereNotIn('status', [Booking::PENDING, Booking::HELD])->get()->each(fn (Booking $b) => $this->folio->sync($b));

        FolioEntry::query()->with('booking:id,reference')->orderBy('id')->each(function (FolioEntry $e): void {
            $key = 'folio:'.$e->id;
            $date = ($e->service_date ?? $e->created_at)->toDateString();
            $memo = $e->description.' · '.$e->booking?->reference;
            $amount = (float) $e->amount;

            if (! $this->ledger->has($key)) {
                match ($e->type) {
                    FolioEntry::CHARGE => $this->ledger->post($date, $memo, $amount >= 0
                        ? [['guest_receivables', $amount, 0], [$this->revenueFor($e->category), 0, $amount]]
                        : [[$this->revenueFor($e->category), -$amount, 0], ['guest_receivables', 0, -$amount]], $key, $e->booking?->reference),
                    FolioEntry::PAYMENT => $this->ledger->post($date, $memo, [[$this->moneyFor($e->category), $amount, 0], ['guest_receivables', 0, $amount]], $key, $e->booking?->reference),
                    FolioEntry::REFUND => $this->ledger->post($date, $memo, [['guest_receivables', $amount, 0], [$this->moneyFor($e->category), 0, $amount]], $key, $e->booking?->reference),
                    default => null,
                };
            }

            if ($e->voided_at && ! $this->ledger->has($key.':void')) {
                $this->ledger->reverse($key, $key.':void', $e->voided_at->toDateString(), 'Void: '.$memo);
            }
        });
    }

    private function orders(): void
    {
        Order::query()->whereIn('status', [Order::COMPLETED, Order::REFUNDED])->where('payment_method', '!=', Order::PAY_ROOM)->orderBy('id')->each(function (Order $o): void {
            $key = 'order:'.$o->id;
            $date = ($o->completed_at ?? $o->updated_at)->toDateString();

            if ($o->completed_at && ! $this->ledger->has($key)) {
                $tax = (float) $o->tax_total;
                $fee = (float) $o->delivery_fee;
                $debits = $this->orderMoney($o);

                $this->ledger->post($date, 'Food order '.$o->reference, array_merge(
                    array_map(fn ($account, $amount) => [$account, $amount, 0], array_keys($debits), $debits),
                    [['fnb_revenue', 0, (float) $o->total - $tax - $fee], ['vat_output', 0, $tax], ['other_revenue', 0, $fee]],
                ), $key, $o->reference);
            }

            if ($o->status === Order::REFUNDED && ! $this->ledger->has($key.':refund')) {
                $this->ledger->reverse($key, $key.':refund', $o->updated_at->toDateString(), 'Refund: food order '.$o->reference);
            }
        });
    }

    /** Where an order's money sits: online → platform wallet, POS → by payment method, cash on pickup → cash. @return array<string, float> */
    private function orderMoney(Order $o): array
    {
        if ($o->payment_method === Order::PAY_ONLINE) {
            return ['platform_wallet' => (float) $o->total];
        }

        if ($o->payment_method === Order::PAY_POS) {
            return PosPayment::query()->where('order_id', $o->id)->where('amount', '>', 0)->get()
                ->groupBy(fn ($p) => match ($p->method) { 'cash' => 'cash', 'gift_card' => 'gift_card_liability', default => 'bank' })
                ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))->all();
        }

        return ['cash' => (float) $o->total];
    }

    private function wallet(): void
    {
        Commission::query()->with(['booking:id,reference', 'order:id,reference'])->orderBy('id')->each(function (Commission $c): void {
            $key = 'commission:'.$c->id;
            $this->ledger->post($c->created_at->toDateString(), 'Platform commission · '.$c->sourceLabel(), [['commission_expense', (float) $c->platform_fee, 0], ['platform_wallet', 0, (float) $c->platform_fee]], $key);

            if ($c->status === Commission::REVERSED && $c->reversed_at && ! $this->ledger->has($key.':reversal')) {
                $this->ledger->reverse($key, $key.':reversal', $c->reversed_at->toDateString(), 'Commission reversed · '.$c->sourceLabel());
            }
        });

        Payout::query()->where('status', Payout::PAID)->orderBy('id')->each(fn (Payout $p) => $this->ledger->post(
            ($p->processed_at ?? $p->updated_at)->toDateString(), 'Payout #'.$p->id.' to bank', [['bank', (float) $p->amount, 0], ['platform_wallet', 0, (float) $p->amount]], 'payout:'.$p->id, $p->reference,
        ));
    }

    private function stock(): void
    {
        $orders = PurchaseOrder::query()->pluck('supplier_id', 'reference');

        StockMovement::query()->whereNotIn('type', ['transfer_in', 'transfer_out'])->orderBy('id')->each(function (StockMovement $m) use ($orders): void {
            $key = 'stock:'.$m->id;
            $value = round(abs((float) $m->quantity) * (float) $m->unit_cost, 2);
            $date = $m->created_at->toDateString();
            $supplier = $m->reference ? ($orders[$m->reference] ?? null) : null;

            [$debit, $credit, $party] = match ($m->type) {
                'receipt' => ['inventory', $supplier ? 'payables' : 'cash', $supplier ? ['supplier', $supplier] : null],
                'sale' => ['cogs', 'inventory', null],
                'sale_return' => ['inventory', 'cogs', null],
                'waste' => ['waste', 'inventory', null],
                'issue' => ['supplies_expense', 'inventory', null],
                'adjustment' => (float) $m->quantity < 0 ? ['waste', 'inventory', null] : ['inventory', 'waste', null],
                default => [null, null, null],
            };

            if ($debit) {
                $this->ledger->post($date, ucfirst(str_replace('_', ' ', $m->type)).' · stock #'.$m->inventory_item_id, [[$debit, $value, 0], [$credit, 0, $value]], $key, $m->reference, $party);
            }
        });
    }

    private function maintenance(): void
    {
        MaintenanceTicket::query()->whereIn('status', [MaintenanceTicket::RESOLVED, MaintenanceTicket::CLOSED])->where('cost', '>', 0)->orderBy('id')->each(fn (MaintenanceTicket $t) => $this->ledger->post(
            ($t->resolved_at ?? $t->updated_at)->toDateString(), 'Repair '.$t->reference.' · '.$t->title, [['maintenance_expense', (float) $t->cost, 0], ['payables', 0, (float) $t->cost]], 'maintenance:'.$t->id, $t->reference,
        ));
    }

    private function revenueFor(string $category): string
    {
        return match ($category) {
            'room' => 'room_revenue',
            'food', 'room_service', 'minibar' => 'fnb_revenue',
            default => 'other_revenue',
        };
    }

    private function moneyFor(string $method): string
    {
        return match ($method) {
            'online' => 'platform_wallet',
            'cash' => 'cash',
            'gift_card' => 'gift_card_liability',
            default => 'bank',
        };
    }
}
