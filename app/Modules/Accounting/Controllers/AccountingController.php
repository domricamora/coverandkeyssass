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

/** Financial reports and the ledger (Phase 20). Every view first syncs the automatic postings. */
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

        $open = Invoice::query()->outstanding()->get();

        return view('accounting::index', [
            'from' => $from, 'to' => $to,
            'pnl' => $this->ledger->profitAndLoss($from, $to),
            'vat' => $this->ledger->vat($from, $to),
            'cash' => $this->ledger->balance('cash'),
            'bank' => $this->ledger->balance('bank'),
            'platformWallet' => $this->ledger->balance('platform_wallet'),
            'guestReceivables' => $this->ledger->balance('guest_receivables'),
            'receivables' => $this->ledger->balance('receivables'),
            'payables' => $this->ledger->balance('payables'),
            'overdue' => $open->filter->isOverdue()->count(),
            'commissions' => round((float) Commission::query()->where('status', '!=', Commission::REVERSED)->whereBetween('created_at', [$from, $to.' 23:59:59'])->sum('platform_fee'), 2),
            'payouts' => round((float) Payout::query()->where('status', Payout::PAID)->whereBetween('processed_at', [$from, $to.' 23:59:59'])->sum('amount'), 2),
            'title' => 'Accounting',
        ]);
    }

    public function trialBalance(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');
        $this->posting->sync();
        $asOf = $request->query('as_of') ?: today()->toDateString();

        return view('accounting::trial-balance', ['asOf' => $asOf, 'tb' => $this->ledger->trialBalance($asOf), 'title' => 'Trial balance']);
    }

    public function journal(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');
        $this->posting->sync();

        return view('accounting::journal', [
            'entries' => JournalEntry::query()->with('lines.account')
                ->when($request->query('account'), fn ($q, $a) => $q->whereHas('lines', fn ($l) => $l->where('ledger_account_id', $a)))
                ->latest('entry_date')->latest('id')->paginate(50)->withQueryString(),
            'accounts' => LedgerAccount::query()->orderBy('code')->get(),
            'title' => 'Journal',
        ]);
    }

    public function payables(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');
        $this->posting->sync();

        $owed = $this->ledger->payablesBySupplier();

        return view('accounting::payables', [
            'suppliers' => Supplier::query()->orderBy('name')->get()->map(fn ($s) => ['supplier' => $s, 'owed' => (float) ($owed[$s->id] ?? 0)]),
            'otherPayables' => round($this->ledger->balance('payables') - $owed->sum(), 2),
            'receivables' => Invoice::query()->outstanding()->orderBy('due_date')->get(),
            'title' => 'Payables & receivables',
        ]);
    }

    public function paySupplier(Request $request, string $supplier)
    {
        $this->authorizeTo($request, 'accounting.manage');

        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(['cash', 'bank'])], 'paid_on' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:120']]);
        $this->books->paySupplier(Supplier::query()->findOrFail($supplier), (float) $validated['amount'], $validated['method'], $validated['paid_on'], $request->user(), $validated['reference'] ?? null);

        return back()->with('success', 'Supplier payment recorded.');
    }

    /** @return array{0: string, 1: string} */
    private function period(Request $request): array
    {
        $from = rescue(fn () => CarbonImmutable::parse((string) $request->query('from')), today()->startOfMonth()->toImmutable(), false);
        $to = rescue(fn () => CarbonImmutable::parse((string) $request->query('to')), today()->toImmutable(), false);

        return [$from->toDateString(), $to->toDateString()];
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
