{{-- Payment panel for a booking page. $payments, $booking; $guest = customer view. --}}
@php
    $guest = $guest ?? false;
    $isPaid = $payments->contains('status', 'paid');
    $canPay = $guest && \App\Modules\Payments\Services\PaymentService::enabled()
        && in_array($booking->status, ['pending', 'held'], true) && ! $isPaid;
@endphp

@if ($canPay)
    <form method="POST" action="{{ route('account.payments.pay', $booking->reference) }}" style="margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        @csrf
        @foreach (\App\Modules\Payments\Services\PaymentService::providers() as [$key, $label])
            <button class="btn {{ $loop->first ? 'btn-primary' : 'btn-outline' }}" type="submit" name="provider" value="{{ $key }}">Pay {{ $booking->money($booking->total) }} · {{ $label }}</button>
        @endforeach
        <span class="muted">Your booking is confirmed as soon as the payment clears.</span>
    </form>
@endif

@error('payment') <p class="muted" style="color:#b42318;">{{ $message }}</p> @enderror

@if ($payments->isNotEmpty())
    <div class="card {{ $guest ? '' : 'p-6 mt-6' }}" style="{{ $guest ? 'padding:18px;margin-bottom:16px;' : '' }}">
        <h2 class="{{ $guest ? '' : 'text-lg' }}" style="{{ $guest ? 'font-size:1.1rem;margin-top:0;' : '' }}">Payments</h2>
        <table class="table">
            <thead><tr><th>Date</th><th>Method</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ ($payment->paid_at ?? $payment->created_at)->format('M j, Y H:i') }}</td>
                        <td>{{ $payment->method ? Str::headline($payment->method) : 'PayMongo' }}</td>
                        <td><span class="badge {{ $payment->badge() }}">{{ ucfirst($payment->status) }}</span>
                            @if ($payment->failure_reason)<br><small class="muted">{{ $payment->failure_reason }}</small>@endif</td>
                        <td class="text-right">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
