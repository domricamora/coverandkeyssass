@php
    $canBuy = auth()->user()->hasPermissionTo('purchasing.manage');
    $canReceive = auth()->user()->hasPermissionTo('inventory.manage') && in_array($po->status, ['ordered', 'partially_received'], true);
@endphp
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $po->reference }} <span class="badge badge-gray">{{ $po->statusLabel() }}</span></h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $po->supplier?->name }} → {{ $po->location?->name }}{{ $po->expected_on ? ' · expected '.$po->expected_on->format('M j') : '' }}</p>
        </div>
        <div class="flex gap-2">
            @if ($canBuy && $po->status === 'draft')
                <form method="POST" action="{{ route('inventory.purchase-orders.order', $po->reference) }}">@csrf<button class="btn btn-primary" type="submit">Mark ordered</button></form>
            @endif
            @if ($canBuy && in_array($po->status, ['draft', 'ordered'], true))
                <form method="POST" action="{{ route('inventory.purchase-orders.cancel', $po->reference) }}">@csrf<button class="btn btn-ghost" type="submit">Cancel</button></form>
            @endif
            <a href="{{ route('inventory.purchase-orders.index') }}" class="btn btn-ghost">All POs</a>
        </div>
    </div>
    @include('inventory::partials.nav')
    <x-input-error :messages="$errors->get('received')" />
    <x-input-error :messages="$errors->get('status')" />

    <form method="POST" action="{{ route('inventory.purchase-orders.receive', $po->reference) }}" class="table-wrap card">
        @csrf
        <table class="table">
            <thead><tr><th>Item</th><th class="text-right">Ordered</th><th class="text-right">Received</th><th class="text-right">Unit cost</th><th class="text-right">Line</th>@if ($canReceive)<th>Receive now</th>@endif</tr></thead>
            <tbody>
                @foreach ($po->lines as $line)
                    <tr>
                        <td>{{ $line->item?->name }}</td>
                        <td class="text-right">{{ $line->item?->qty($line->quantity) }}</td>
                        <td class="text-right">{{ $line->item?->qty($line->received_quantity) }}</td>
                        <td class="text-right">₱{{ number_format((float) $line->unit_cost, 2) }}</td>
                        <td class="text-right">₱{{ number_format((float) $line->quantity * (float) $line->unit_cost, 2) }}</td>
                        @if ($canReceive)
                            <td><input name="received[{{ $line->id }}]" type="number" step="0.001" min="0" max="{{ $line->outstanding() }}" value="{{ $line->outstanding() }}" class="form-input" style="width:110px" aria-label="Receive {{ $line->item?->name }}" /></td>
                        @endif
                    </tr>
                @endforeach
                <tr><td colspan="4"><strong>Total</strong></td><td class="text-right"><strong>₱{{ number_format((float) $po->total, 2) }}</strong></td>@if ($canReceive)<td><button type="submit" class="btn btn-primary">Receive</button></td>@endif</tr>
            </tbody>
        </table>
    </form>
</x-app-layout>
