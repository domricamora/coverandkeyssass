{{-- Order lines + totals. $order, $items. Shared by the customer and host order pages. --}}
<table class="table" style="width:100%;">
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td>
                    {{ $item->quantity }} × <strong>{{ $item->name }}</strong>
                    @if ($item->modifiers)<br><small class="muted" style="color:var(--text-3)">{{ $item->modifierLabel() }}</small>@endif
                    @if ($item->notes)<br><small class="muted" style="color:var(--text-3)">“{{ $item->notes }}”</small>@endif
                </td>
                <td style="text-align:right;">{{ $order->money($item->line_total) }}</td>
            </tr>
        @endforeach
        <tr><td>Subtotal</td><td style="text-align:right;">{{ $order->money($order->subtotal) }}</td></tr>
        @if ((float) $order->discount_total > 0)
            <tr><td>Discount</td><td style="text-align:right;">−{{ $order->money($order->discount_total) }}</td></tr>
        @endif
        <tr><td>Tax ({{ (float) $order->tax_rate }}% {{ $order->tax_inclusive ? 'included' : 'added' }})</td><td style="text-align:right;">{{ $order->money($order->tax_total) }}</td></tr>
        @if ((float) $order->delivery_fee > 0)
            <tr><td>Delivery fee</td><td style="text-align:right;">{{ $order->money($order->delivery_fee) }}</td></tr>
        @endif
        <tr><td><strong>Total</strong></td><td style="text-align:right;"><strong>{{ $order->money($order->total) }}</strong></td></tr>
    </tbody>
</table>
