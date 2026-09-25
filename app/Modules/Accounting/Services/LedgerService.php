<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Models\LedgerAccount;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The general ledger (Phase 20). post() is the only way money enters the
 * books: it refuses unbalanced entries and is idempotent by source_key, so
 * the automatic postings can be re-run safely. Corrections are reversing
 * entries — nothing is ever edited or deleted.
 */
class LedgerService
{
    /** @var array<int, array<string, LedgerAccount>> tenant id => system key => account */
    private array $accounts = [];

    /** The active business's chart, created with the defaults on first use. */
    public function account(string $systemKey): LedgerAccount
    {
        $tenantId = (int) app(TenantContext::class)->id();

        if (! isset($this->accounts[$tenantId])) {
            foreach (LedgerAccount::DEFAULTS as $key => [$code, $name, $type]) {
                LedgerAccount::query()->firstOrCreate(['system_key' => $key], ['code' => $code, 'name' => $name, 'type' => $type]);
            }
            $this->accounts[$tenantId] = LedgerAccount::query()->whereNotNull('system_key')->get()->keyBy('system_key')->all();
        }

        return $this->accounts[$tenantId][$systemKey] ?? throw new \InvalidArgumentException("Unknown account {$systemKey}.");
    }

    /**
     * @param  list<array{0: string|LedgerAccount, 1: float, 2: float}>  $lines  [account, debit, credit]
     */
    public function post(string $date, string $memo, array $lines, ?string $sourceKey = null, ?string $reference = null, ?array $party = null, ?User $by = null): ?JournalEntry
    {
        $resolved = [];
        foreach ($lines as [$account, $debit, $credit]) {
            $debit = round(max(0, $debit), 2);
            $credit = round(max(0, $credit), 2);
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }
            $resolved[] = [$account instanceof LedgerAccount ? $account : $this->account($account), $debit, $credit];
        }

        $debits = round(array_sum(array_column($resolved, 1)), 2);
        $credits = round(array_sum(array_column($resolved, 2)), 2);

        if ($resolved === []) {
            return null; // nothing to book (zero amounts)
        }

        // Already booked under this source key: idempotent no-op, no query.
        if ($sourceKey && $this->has($sourceKey)) {
            return null;
        }

        if (abs($debits - $credits) > 0.004) {
            throw ValidationException::withMessages(['journal' => "Unbalanced entry \"{$memo}\": debits {$debits} ≠ credits {$credits}."]);
        }

        return DB::transaction(function () use ($date, $memo, $resolved, $sourceKey, $reference, $party, $by) {
            if ($sourceKey && ($existing = JournalEntry::query()->where('source_key', $sourceKey)->first())) {
                return $existing;
            }

            $entry = JournalEntry::create([
                'entry_date' => $date,
                'memo' => mb_substr($memo, 0, 255),
                'reference' => $reference,
                'source_key' => $sourceKey,
                'party_type' => $party[0] ?? null,
                'party_id' => $party[1] ?? null,
                'created_by' => $by?->id,
            ]);

            foreach ($resolved as [$account, $debit, $credit]) {
                $entry->lines()->create(['ledger_account_id' => $account->id, 'debit' => $debit, 'credit' => $credit]);
            }

            if ($sourceKey) {
                $this->keyMemo[app(\App\Support\TenantContext::class)->id() ?? 0][$sourceKey] = true;
            }

            return $entry;
        });
    }

    /** Mirror an entry (debits ↔ credits) under a new source key. */
    public function reverse(string $originalKey, string $reversalKey, string $date, string $memo): ?JournalEntry
    {
        $original = JournalEntry::query()->where('source_key', $originalKey)->with('lines.account')->first();

        if (! $original) {
            return null;
        }

        return $this->post($date, $memo, $original->lines->map(fn (JournalLine $l) => [$l->account, (float) $l->credit, (float) $l->debit])->all(),
            $reversalKey, $original->reference, $original->party_type ? [$original->party_type, $original->party_id] : null);
    }

    public function has(string $sourceKey): bool
    {
        return isset($this->postedKeys()[$sourceKey]);
    }

    /**
     * Source keys already booked for the current business, loaded once per
     * service instance: a sync touches every source row, and one query per
     * row made the report screens (which sync on view) grow with history.
     *
     * @return array<string, true>
     */
    private function postedKeys(): array
    {
        return $this->keyMemo[app(\App\Support\TenantContext::class)->id() ?? 0] ??= JournalEntry::query()
            ->whereNotNull('source_key')
            ->pluck('source_key')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /** @var array<int, array<string, true>> */
    private array $keyMemo = [];

    // ------------------------------------------------------------------
    // Reports
    // ------------------------------------------------------------------

    /** Net (debit − credit) per account id for entries in [from, to] (either open). @return Collection<int, float> */
    public function movements(?string $from = null, ?string $to = null): Collection
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($from, fn ($q) => $q->where('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('journal_entries.entry_date', '<=', $to))
            ->groupBy('journal_lines.ledger_account_id')
            ->selectRaw('journal_lines.ledger_account_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) AS net')
            ->pluck('net', 'ledger_account_id')
            ->map(fn ($v) => round((float) $v, 2));
    }

    /** @return array{revenue: list<array>, expenses: list<array>, total_revenue: float, total_expenses: float, net: float} */
    public function profitAndLoss(string $from, string $to): array
    {
        $this->account('cash'); // make sure the chart exists
        $net = $this->movements($from, $to);
        $rows = fn (string $type, int $sign) => LedgerAccount::query()->where('type', $type)->orderBy('code')->get()
            ->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'amount' => round($sign * ($net[$a->id] ?? 0), 2)])
            ->filter(fn ($r) => $r['amount'] != 0.0)->values()->all();

        $revenue = $rows('revenue', -1);
        $expenses = $rows('expense', 1);
        $totalRevenue = round(array_sum(array_column($revenue, 'amount')), 2);
        $totalExpenses = round(array_sum(array_column($expenses, 'amount')), 2);

        return ['revenue' => $revenue, 'expenses' => $expenses, 'total_revenue' => $totalRevenue, 'total_expenses' => $totalExpenses, 'net' => round($totalRevenue - $totalExpenses, 2)];
    }

    /** Balances as of a date; debits and credits always agree. @return array{rows: list<array>, debits: float, credits: float} */
    public function trialBalance(?string $asOf = null): array
    {
        $this->account('cash');
        $net = $this->movements(null, $asOf);
        $rows = LedgerAccount::query()->orderBy('code')->get()
            ->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'type' => $a->type, 'debit' => max(0, $net[$a->id] ?? 0), 'credit' => max(0, -($net[$a->id] ?? 0))])
            ->filter(fn ($r) => $r['debit'] != 0.0 || $r['credit'] != 0.0)->values()->all();

        return ['rows' => $rows, 'debits' => round(array_sum(array_column($rows, 'debit')), 2), 'credits' => round(array_sum(array_column($rows, 'credit')), 2)];
    }

    /** @return array{output: float, input: float, payable: float} */
    public function vat(string $from, string $to): array
    {
        $net = $this->movements($from, $to);
        $output = round(-($net[$this->account('vat_output')->id] ?? 0), 2);
        $input = round($net[$this->account('vat_input')->id] ?? 0, 2);

        return ['output' => $output, 'input' => $input, 'payable' => round($output - $input, 2)];
    }

    /** Balance of one system account (debit-positive for assets / expenses). */
    public function balance(string $systemKey, ?string $asOf = null): float
    {
        $account = $this->account($systemKey);
        $net = (float) ($this->movements(null, $asOf)[$account->id] ?? 0);

        return round($account->isDebitNormal() ? $net : -$net, 2);
    }

    /** Accounts payable per supplier (credit-positive). @return Collection<int, float> */
    public function payablesBySupplier(): Collection
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.ledger_account_id', $this->account('payables')->id)
            ->where('journal_entries.party_type', 'supplier')
            ->groupBy('journal_entries.party_id')
            ->selectRaw('journal_entries.party_id, SUM(journal_lines.credit) - SUM(journal_lines.debit) AS owed')
            ->pluck('owed', 'party_id')
            ->map(fn ($v) => round((float) $v, 2));
    }
}
