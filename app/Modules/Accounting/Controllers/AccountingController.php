<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\LedgerAccount;
use App\Modules\Accounting\Services\BooksService;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Wallet\Models\Commission;
use App\Modules\Wallet\Models\Payout;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Financial reports and the ledger (Phase 20; React screens). Every view first syncs the automatic postings. */
class AccountingController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PostingService $posting,
        private readonly BooksService $books,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');
        $this->posting->sync();
        [$from, $to] = $this->period($request);

        return Inertia::render('Accounting/Index', [
            'from' => $from,
            'to' => $to,
            'pnl' => $this->ledger->profitAndLoss($from, $to),
            'vat' => $this->ledger->vat($from, $to),
            'balances' => [
                ['Cash on hand', $this->ledger->balance('cash')],
                ['Bank', $this->ledger->balance('bank')],
                ['Platform wallet', $this->ledger->balance('platform_wallet')],
                ['Guest folios open', $this->ledger->balance('guest_receivables')],
            ],
            'receivables' => $this->ledger->balance('receivables'),
            'payables' => $this->ledger->balance('payables'),
            'overdue' => Invoice::query()->outstanding()->get()->filter->isOverdue()->count(),
            'commissions' => round((float) Commission::query()->where('status', '!=', Commission::REVERSED)->whereBetween('created_at', [$from, $to.' 23:59:59'])->sum('platform_fee'), 2),
            'payouts' => round((float) Payout::query()->where('status', Payout::PAID)->whereBetween('processed_at', [$from, $to.' 23:59:59'])->sum('amount'), 2),
            'tabs' => self::tabs('accounting.index'),
            'urls' => ['self' => route('accounting.index'), 'payables' => route('accounting.payables')],
        ]);
    }

    public function trialBalance(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');
        $this->posting->sync();
        $asOf = $request->query('as_of') ?: today()->toDateString();

        return Inertia::render('Accounting/TrialBalance', [
            'asOf' => $asOf,
            'tb' => $this->ledger->trialBalance($asOf),
            'tabs' => self::tabs('accounting.trial-balance'),
            'urls' => ['self' => route('accounting.trial-balance')],
        ]);
    }

    public function journal(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');
        $this->posting->sync();

        $entries = JournalEntry::query()->with('lines.account')
            ->when($request->query('account'), fn ($q, $a) => $q->whereHas('lines', fn ($l) => $l->where('ledger_account_id', $a)))
            ->latest('entry_date')->latest('id')->paginate(50)->withQueryString();

        return Inertia::render('Accounting/Journal', [
            'entries' => $entries->through(fn (JournalEntry $e) => [
                'id' => $e->id,
                'date' => $e->entry_date->format('M j, Y'),
                'memo' => $e->memo,
                'reference' => $e->reference,
                'lines' => $e->lines->map(fn ($l) => [
                    'id' => $l->id,
                    'account' => $l->account ? $l->account->code.' · '.$l->account->name : '—',
                    'debit' => (float) $l->debit,
                    'credit' => (float) $l->credit,
                ]),
            ]),
            'accounts' => LedgerAccount::query()->orderBy('code')->get()->map(fn ($a) => [$a->id, $a->code.' · '.$a->name]),
            'account' => $request->query('account'),
            'tabs' => self::tabs('accounting.journal'),
            'urls' => ['self' => route('accounting.journal')],
        ]);
    }

    public function payables(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');
        $this->posting->sync();

        $owed = $this->ledger->payablesBySupplier();

        return Inertia::render('Accounting/Payables', [
            'suppliers' => Supplier::query()->orderBy('name')->get()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'owed' => (float) ($owed[$s->id] ?? 0),
                'pay' => route('accounting.suppliers.pay', $s->id),
            ]),
            'otherPayables' => round($this->ledger->balance('payables') - $owed->sum(), 2),
            'receivables' => Invoice::query()->outstanding()->orderBy('due_date')->get()->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'customer' => $i->customer_name,
                'due' => $i->due_date->format('M j'),
                'overdueDays' => $i->isOverdue() ? (int) $i->due_date->diffInDays(today()) : null,
                'balance' => $i->balance(),
                'href' => route('accounting.invoices.show', $i->id),
            ]),
            'today' => today()->toDateString(),
            'can' => ['manage' => $request->user()->hasPermissionTo('accounting.manage')],
            'tabs' => self::tabs('accounting.payables'),
        ]);
    }

    public function paySupplier(Request $request, string $supplier)
    {
        $this->authorizeTo($request, 'accounting.manage');

        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(['cash', 'bank'])], 'paid_on' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:120']]);
        $this->books->paySupplier(Supplier::query()->findOrFail($supplier), (float) $validated['amount'], $validated['method'], $validated['paid_on'], $request->user(), $validated['reference'] ?? null);

        return back()->with('success', 'Supplier payment recorded.');
    }

    /** Sub-navigation shared by the accounting screens (React `Tabs`). */
    public static function tabs(string $current): array
    {
        return collect([
            ['accounting.index', 'Overview'], ['accounting.invoices', 'Invoices'], ['accounting.expenses', 'Expenses'],
            ['accounting.payables', 'Payables & receivables'], ['accounting.journal', 'Journal'], ['accounting.trial-balance', 'Trial balance'],
        ])->map(fn ($t) => ['label' => $t[1], 'href' => route($t[0]), 'active' => $t[0] === $current])->all();
    }

    /** @return array{0: string, 1: string} */
    private function period(Request $request): array
    {
        // parse('') is "now", not an error: an empty value must fall back to the default.
        $date = fn (string $key, $default) => filled($request->query($key)) ? rescue(fn () => CarbonImmutable::parse((string) $request->query($key)), $default, false) : $default;
        $from = $date('from', today()->startOfMonth()->toImmutable());
        $to = $date('to', today()->toImmutable());

        return [$from->toDateString(), $to->toDateString()];
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
