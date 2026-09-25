<nav class="flex flex-wrap gap-2 mt-2 mb-4" aria-label="Accounting">
    @foreach ([['accounting.index', 'Overview'], ['accounting.invoices', 'Invoices'], ['accounting.expenses', 'Expenses'], ['accounting.payables', 'Payables & receivables'], ['accounting.journal', 'Journal'], ['accounting.trial-balance', 'Trial balance']] as [$name, $label])
        <a href="{{ route($name) }}" class="btn btn-sm {{ request()->routeIs($name) ? 'btn-dark' : 'btn-ghost' }}">{{ $label }}</a>
    @endforeach
</nav>
