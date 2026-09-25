<x-app-layout>
    <div class="dash-row-head">
        <div><h1>Journal</h1><p class="mt-1 text-sm" style="color:var(--text-3)">Every posting, newest first.</p></div>
        <form method="GET">
            <select name="account" class="form-input" onchange="this.form.submit()" aria-label="Account">
                <option value="">All accounts</option>
                @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(request('account') == $a->id)>{{ $a->code }} · {{ $a->name }}</option>@endforeach
            </select>
        </form>
    </div>
    @include('accounting::partials.nav')

    <div class="table-wrap card">
        <table class="table">
            <thead><tr><th>Date</th><th>Entry</th><th>Account</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    @foreach ($entry->lines as $i => $line)
                        <tr>
                            <td>{{ $i === 0 ? $entry->entry_date->format('M j, Y') : '' }}</td>
                            <td>@if ($i === 0){{ $entry->memo }}@if ($entry->reference)<br><small style="color:var(--text-3)">{{ $entry->reference }}</small>@endif @endif</td>
                            <td style="{{ (float) $line->credit > 0 ? 'padding-left:24px' : '' }}">{{ $line->account?->code }} · {{ $line->account?->name }}</td>
                            <td class="text-right">{{ (float) $line->debit ? number_format((float) $line->debit, 2) : '' }}</td>
                            <td class="text-right">{{ (float) $line->credit ? number_format((float) $line->credit, 2) : '' }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm" style="color:var(--text-3)">Nothing posted yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $entries->links() }}</div>
    </div>
</x-app-layout>
