<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\LedgerAccount;
use App\Modules\Accounting\Services\BooksService;
use App\Modules\Accounting\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Expenses and customer invoices (Phase 20). */
class BooksController extends Controller
{
    public function __construct(
        private readonly BooksService $books,
        private readonly LedgerService $ledger,
    ) {}

    public function expenses(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');
        $this->ledger->account('cash');

        return view('accounting::expenses', [
            'expenses' => Expense::query()->with('account')->latest('expense_date')->latest('id')->paginate(30),
            'accounts' => LedgerAccount::query()->where('type', 'expense')->orderBy('code')->get(),
            'title' => 'Expenses',
        ]);
    }

    public function storeExpense(Request $request)
    {
        $this->authorizeTo($request, 'accounting.manage');

        $validated = $request->validate([
            'ledger_account_id' => ['required', 'integer'],
            'vendor' => ['required', 'string', 'max:160'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_from' => ['required', Rule::in(Expense::PAID_FROM)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->books->recordExpense(LedgerAccount::query()->findOrFail($validated['ledger_account_id']), $validated['vendor'], $validated['expense_date'],
            (float) $validated['amount'], (float) ($validated['tax_amount'] ?? 0), $validated['paid_from'], $request->user(), $validated['reference'] ?? null, $validated['notes'] ?? null);

        return back()->with('success', 'Expense booked.');
    }

    public function invoices(Request $request)
    {
        $this->authorizeTo($request, 'accounting.view');

        return view('accounting::invoices', ['invoices' => Invoice::query()->latest('issue_date')->latest('id')->paginate(30), 'title' => 'Invoices']);
    }

    public function storeInvoice(Request $request)
    {
        $this->authorizeTo($request, 'accounting.manage');

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = $this->books->createInvoice(collect($validated)->except('lines')->all() + ['tax_rate' => $validated['tax_rate'] ?? 0], $validated['lines'], $request->user());

        return redirect()->route('accounting.invoices.show', $invoice->id)->with('success', 'Invoice '.$invoice->number.' drafted.');
    }

    public function showInvoice(Request $request, string $invoice)
    {
        $this->authorizeTo($request, 'accounting.view');

        return view('accounting::invoice', ['invoice' => Invoice::query()->with(['lines', 'payments'])->findOrFail($invoice), 'title' => 'Invoice']);
    }

    public function issueInvoice(Request $request, string $invoice)
    {
        $this->authorizeTo($request, 'accounting.manage');
        $this->books->issue(Invoice::query()->findOrFail($invoice));

        return back()->with('success', 'Invoice issued.');
    }

    public function payInvoice(Request $request, string $invoice)
    {
        $this->authorizeTo($request, 'accounting.manage');

        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(['cash', 'bank'])], 'paid_on' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:120']]);
        $this->books->receivePayment(Invoice::query()->findOrFail($invoice), (float) $validated['amount'], $validated['method'], $validated['paid_on'], $validated['reference'] ?? null);

        return back()->with('success', 'Payment received.');
    }

    public function voidInvoice(Request $request, string $invoice)
    {
        $this->authorizeTo($request, 'accounting.manage');
        $this->books->void(Invoice::query()->findOrFail($invoice));

        return back()->with('success', 'Invoice voided.');
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
