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
use Inertia\Inertia;

/** Expenses and customer invoices (Phase 20; React screens). */
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

        return Inertia::render('Accounting/Expenses', [
            'expenses' => Expense::query()->with('account')->latest('expense_date')->latest('id')->paginate(30)->through(fn (Expense $e) => [
                'id' => $e->id,
                'date' => $e->expense_date->format('M j, Y'),
                'vendor' => $e->vendor,
                'reference' => $e->reference,
                'account' => $e->account?->name,
                'paidFrom' => $e->paid_from,
                'tax' => (float) $e->tax_amount,
                'amount' => (float) $e->amount,
            ]),
            'accounts' => LedgerAccount::query()->where('type', 'expense')->orderBy('code')->get()->map(fn ($a) => [$a->id, $a->code.' · '.$a->name]),
            'today' => today()->toDateString(),
            'can' => ['manage' => $request->user()->hasPermissionTo('accounting.manage')],
            'tabs' => AccountingController::tabs('accounting.expenses'),
            'urls' => ['store' => route('accounting.expenses.store')],
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

        return Inertia::render('Accounting/Invoices', [
            'invoices' => Invoice::query()->latest('issue_date')->latest('id')->paginate(30)->through(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'customer' => $i->customer_name,
                'due' => $i->due_date->format('M j, Y'),
                'status' => $i->isOverdue() ? 'overdue' : $i->status,
                'total' => (float) $i->total,
                'balance' => $i->balance(),
                'href' => route('accounting.invoices.show', $i->id),
            ]),
            'today' => today()->toDateString(),
            'due' => today()->addDays(30)->toDateString(),
            'can' => ['manage' => $request->user()->hasPermissionTo('accounting.manage')],
            'tabs' => AccountingController::tabs('accounting.invoices'),
            'urls' => ['store' => route('accounting.invoices.store')],
        ]);
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
        $invoice = Invoice::query()->with(['lines', 'payments'])->findOrFail($invoice);
        $canManage = $request->user()->hasPermissionTo('accounting.manage');

        return Inertia::render('Accounting/Invoice', [
            'invoice' => [
                'number' => $invoice->number,
                'customer' => $invoice->customer_name,
                'email' => $invoice->customer_email,
                'issued' => $invoice->issue_date->format('M j, Y'),
                'due' => $invoice->due_date->format('M j, Y'),
                'status' => $invoice->isOverdue() ? 'overdue' : $invoice->status,
                'taxRate' => (float) $invoice->tax_rate,
                'subtotal' => (float) $invoice->subtotal,
                'tax' => (float) $invoice->tax_total,
                'total' => (float) $invoice->total,
                'paid' => (float) $invoice->amount_paid,
                'balance' => $invoice->balance(),
                'notes' => $invoice->notes,
                'lines' => $invoice->lines->map(fn ($l) => ['id' => $l->id, 'description' => $l->description, 'quantity' => (float) $l->quantity, 'unit' => (float) $l->unit_price, 'total' => (float) $l->line_total]),
                'payments' => $invoice->payments->map(fn ($p) => ['id' => $p->id, 'date' => $p->paid_on->format('M j, Y'), 'method' => $p->method, 'amount' => (float) $p->amount, 'reference' => $p->reference]),
            ],
            'today' => today()->toDateString(),
            'can' => [
                'issue' => $canManage && $invoice->status === Invoice::DRAFT,
                'void' => $canManage && in_array($invoice->status, [Invoice::DRAFT, Invoice::ISSUED], true) && (float) $invoice->amount_paid == 0.0,
                'pay' => $canManage && $invoice->status === Invoice::ISSUED,
            ],
            'tabs' => AccountingController::tabs('accounting.invoices'),
            'urls' => [
                'index' => route('accounting.invoices'),
                'issue' => route('accounting.invoices.issue', $invoice->id),
                'void' => route('accounting.invoices.void', $invoice->id),
                'pay' => route('accounting.invoices.pay', $invoice->id),
            ],
        ]);
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
