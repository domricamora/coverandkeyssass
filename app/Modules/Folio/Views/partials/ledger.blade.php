{{-- Folio lines + totals. $entries, $totals; $voidable = show void forms (host). --}}
@php($money = fn ($v) => \App\Support\Currency::symbol().number_format((float) $v, 2))
<table class="table" style="width:100%;">
    <thead><tr><th>Date</th><th>Description</th><th>Category</th><th style="text-align:right;">Amount</th>@if ($voidable ?? false)<th></th>@endif</tr></thead>
    <tbody>
        @foreach (['charge' => 'Charges', 'payment' => 'Payments', 'refund' => 'Refunds'] as $type => $heading)
            @php($rows = $entries->where('type', $type))
            @continue($rows->isEmpty())
            <tr><td colspan="{{ ($voidable ?? false) ? 5 : 4 }}"><strong>{{ $heading }}</strong></td></tr>
            @foreach ($rows as $entry)
                <tr style="{{ $entry->voided_at ? 'opacity:.5;text-decoration:line-through;' : '' }}">
                    <td>{{ $entry->service_date?->format('M j') ?? $entry->created_at->format('M j') }}</td>
                    <td>
                        {{ $entry->description }}
                        @if ((float) $entry->quantity !== 1.0) <small>({{ (float) $entry->quantity }} × {{ $money($entry->unit_amount) }})</small>@endif
                        @if ($entry->reference)<br><small style="color:var(--text-3)">Ref {{ $entry->reference }}</small>@endif
                        @if ($entry->voided_at)<br><small style="color:var(--text-3);text-decoration:none;">Void: {{ $entry->void_reason }}</small>@endif
                    </td>
                    <td>{{ $entry->categoryLabel() }}</td>
                    <td style="text-align:right;">{{ $type === 'charge' ? '' : '−' }}{{ $money($entry->amount) }}</td>
                    @if ($voidable ?? false)
                        <td style="text-align:right;">
                            @if ($entry->isManual() && ! $entry->voided_at)
                                <form method="POST" action="{{ route('folio.void', [$booking->reference, $entry->id]) }}" class="flex gap-1" onsubmit="return this.reason.value !== ''">
                                    @csrf
                                    <input name="reason" type="text" required class="form-input" placeholder="Reason" style="width:120px" aria-label="Void reason" />
                                    <button type="submit" class="btn btn-sm btn-ghost">Void</button>
                                </form>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

<table class="table" style="width:100%;margin-top:12px;">
    <tbody>
        @foreach ($totals['by_category'] as $category => $amount)
            <tr><td>{{ \Illuminate\Support\Str::headline($category) }}</td><td style="text-align:right;">{{ $money($amount) }}</td></tr>
        @endforeach
        <tr><td><strong>Total charges</strong></td><td style="text-align:right;"><strong>{{ $money($totals['charges']) }}</strong></td></tr>
        <tr><td>Payments</td><td style="text-align:right;">−{{ $money($totals['payments']) }}</td></tr>
        @if ($totals['refunds'] > 0)<tr><td>Refunds</td><td style="text-align:right;">+{{ $money($totals['refunds']) }}</td></tr>@endif
        <tr><td><strong>Balance {{ $totals['balance'] < 0 ? '(credit)' : 'due' }}</strong></td><td style="text-align:right;"><strong>{{ $money(abs($totals['balance'])) }}</strong></td></tr>
    </tbody>
</table>
