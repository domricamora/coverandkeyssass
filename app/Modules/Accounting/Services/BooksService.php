<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\LedgerAccount;
use App\Modules\Accounting\Models\SupplierPayment;
use App\Modules\Inventory\Models\Supplier;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hand-entered books (Phase 20): expenses, customer invoices (receivables)
 * and supplier payments (payables). Each document posts its own journal
 * entry in the same transaction, so a document never exists unbooked.
 */
class BooksService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function recordExpense(LedgerAccount $account, string $vendor, string $date, float $amount, float $tax, string $paidFrom, User $by, ?string $reference = null, ?string $notes = null): Expense
    {
        if ($account->type !== 'expense' && $account->system_key !== 'inventory') {
            $this->fail('ledger_account_id', 'Book expenses to an expense account.');
        }

        if ($tax < 0 || $tax >= $amount) {
            $this->fail('tax_amount', 'VAT must be below the gross amount.');
        }

        return DB::transaction(function () use ($account, $vendor, $date, $amount, $tax, $paidFrom, $by, $reference, $notes) {
            $expense = Expense::create([
                'ledger_account_id' => $account->id, 'vendor' => $vendor, 'expense_date' => $date, 'amount' => $amount,
                'tax_amount' => $tax, 'paid_from' => $paidFrom, 'reference' => $reference, 'notes' => $notes, 'created_by' => $by->id,
            ]);

            $this->ledger->post($date, 'Expense · '.$vendor, [
                [$account, $amount - $tax, 0],
                ['vat_input', $tax, 0],
                [$paidFrom === 'payable' ? 'payables' : $paidFrom, 0, $amount],
            ], 'expense:'.$expense->id, $reference, by: $by);

            return $expense;
        });
    }

    /** @param list<array{description: string, quantity: float|string, unit_price: float|string}> $lines */
    public function createInvoice(array $data, array $lines, User $by): Invoice
    {
        $lines = array_values(array_filter($lines, fn ($l) => filled($l['description'] ?? null) && (float) ($l['quantity'] ?? 0) > 0));

        if ($lines === []) {
            $this->fail('lines', 'Add at least one line.');
        }

        return DB::transaction(function () use ($data, $lines, $by) {
            $invoice = Invoice::create($data + ['number' => Invoice::nextNumber(), 'created_by' => $by->id]);

            foreach ($lines as $line) {
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => round((float) $line['quantity'] * (float) $line['unit_price'], 2),
                ]);
            }

            $subtotal = round((float) $invoice->lines()->sum('line_total'), 2);
            $tax = round($subtotal * (float) $invoice->tax_rate / 100, 2);
            $invoice->forceFill(['subtotal' => $subtotal, 'tax_total' => $tax, 'total' => $subtotal + $tax])->save();

            return $invoice;
        });
    }

    /** Draft → issued: the receivable is booked. */
    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== Invoice::DRAFT) {
            $this->fail('status', 'Only drafts can be issued.');
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->forceFill(['status' => Invoice::ISSUED])->save();
            $this->ledger->post($invoice->issue_date->toDateString(), 'Invoice '.$invoice->number.' · '.$invoice->customer_name, [
                ['receivables', (float) $invoice->total, 0],
                ['other_revenue', 0, (float) $invoice->subtotal],
                ['vat_output', 0, (float) $invoice->tax_total],
            ], 'invoice:'.$invoice->id, $invoice->number, ['invoice', $invoice->id]);
        });

        return $invoice;
    }

    public function receivePayment(Invoice $invoice, float $amount, string $method, string $paidOn, ?string $reference = null): Invoice
    {
        if ($invoice->status !== Invoice::ISSUED) {
            $this->fail('amount', 'Only issued invoices take payments.');
        }

        if ($amount <= 0 || $amount > $invoice->balance() + 0.004) {
            $this->fail('amount', 'Enter up to the balance of '.\App\Support\Currency::symbol().number_format($invoice->balance(), 2).'.');
        }

        return DB::transaction(function () use ($invoice, $amount, $method, $paidOn, $reference) {
            $payment = $invoice->payments()->create(['paid_on' => $paidOn, 'amount' => $amount, 'method' => $method, 'reference' => $reference]);
            $this->ledger->post($paidOn, 'Payment · invoice '.$invoice->number, [[$method, $amount, 0], ['receivables', 0, $amount]], 'invoicepay:'.$payment->id, $reference, ['invoice', $invoice->id]);

            $paid = round((float) $invoice->amount_paid + $amount, 2);
            $invoice->forceFill(['amount_paid' => $paid, 'status' => $paid >= (float) $invoice->total ? Invoice::PAID : Invoice::ISSUED])->save();

            return $invoice;
        });
    }

    public function void(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, [Invoice::DRAFT, Invoice::ISSUED], true) || (float) $invoice->amount_paid > 0) {
            $this->fail('status', 'Only unpaid invoices can be voided.');
        }

        DB::transaction(function () use ($invoice): void {
            if ($invoice->status === Invoice::ISSUED) {
                $this->ledger->reverse('invoice:'.$invoice->id, 'invoice:'.$invoice->id.':void', today()->toDateString(), 'Void invoice '.$invoice->number);
            }
            $invoice->forceFill(['status' => Invoice::VOID])->save();
        });

        $this->audit->log('invoice.voided', $invoice, null, ['number' => $invoice->number]);

        return $invoice;
    }

    public function paySupplier(Supplier $supplier, float $amount, string $method, string $paidOn, User $by, ?string $reference = null): SupplierPayment
    {
        $owed = (float) ($this->ledger->payablesBySupplier()[$supplier->id] ?? 0);

        if ($amount <= 0 || $amount > $owed + 0.004) {
            $this->fail('amount', 'You owe '.$supplier->name.' '.\App\Support\Currency::symbol().number_format($owed, 2).'.');
        }

        return DB::transaction(function () use ($supplier, $amount, $method, $paidOn, $by, $reference) {
            $payment = SupplierPayment::create(['supplier_id' => $supplier->id, 'paid_on' => $paidOn, 'amount' => $amount, 'method' => $method, 'reference' => $reference, 'created_by' => $by->id]);
            $this->ledger->post($paidOn, 'Payment to '.$supplier->name, [['payables', $amount, 0], [$method, 0, $amount]], 'supplierpay:'.$payment->id, $reference, ['supplier', $supplier->id], $by);

            return $payment;
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
