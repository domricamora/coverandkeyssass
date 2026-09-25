<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $order->reference }}</title>
    <style>
        body { font-family: ui-monospace, Menlo, Consolas, monospace; width: 300px; margin: 16px auto; color: #111; font-size: 13px; }
        h1 { font-size: 16px; text-align: center; margin: 0; }
        p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .r { text-align: right; }
        .rule { border-top: 1px dashed #999; margin: 6px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print"><button onclick="window.print()">Print</button></p>
    <h1>{{ $restaurant->name }}</h1>
    <p style="text-align:center">{{ $restaurant->address_line }}</p>
    <div class="rule"></div>
    <p>{{ $order->reference }} · {{ $order->table ? 'Table '.$order->table->label : 'Counter' }}</p>
    <p>{{ $order->created_at->format('M j, Y g:i A') }}</p>
    <div class="rule"></div>
    <table>
        @foreach ($order->items as $item)
            <tr><td>{{ $item->quantity }} {{ $item->name }}@if ($item->modifiers)<br><small>{{ $item->modifierLabel() }}</small>@endif</td><td class="r">{{ number_format((float) $item->line_total, 2) }}</td></tr>
        @endforeach
    </table>
    <div class="rule"></div>
    <table>
        <tr><td>Subtotal</td><td class="r">{{ number_format((float) $order->subtotal, 2) }}</td></tr>
        @if ((float) $order->discount_total > 0)<tr><td>Discount{{ $order->discount_reason ? ' ('.$order->discount_reason.')' : '' }}</td><td class="r">-{{ number_format((float) $order->discount_total, 2) }}</td></tr>@endif
        <tr><td>VAT {{ (float) $order->tax_rate }}% {{ $order->tax_inclusive ? 'incl.' : '' }}</td><td class="r">{{ number_format((float) $order->tax_total, 2) }}</td></tr>
        <tr><td><strong>TOTAL</strong></td><td class="r"><strong>₱{{ number_format((float) $order->total, 2) }}</strong></td></tr>
    </table>
    <div class="rule"></div>
    <table>
        @foreach ($payments as $p)
            <tr><td>{{ (float) $p->amount < 0 ? 'Refund' : \Illuminate\Support\Str::headline($p->method) }}</td><td class="r">{{ number_format((float) $p->amount, 2) }}</td></tr>
            @if ($p->tendered)<tr><td>&nbsp; tendered / change</td><td class="r">{{ number_format((float) $p->tendered, 2) }} / {{ number_format((float) $p->change_given, 2) }}</td></tr>@endif
        @endforeach
    </table>
    <div class="rule"></div>
    <p style="text-align:center">{{ $order->paymentLabel() }} — thank you!</p>
</body>
</html>
