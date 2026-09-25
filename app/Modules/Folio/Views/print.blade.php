<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Folio {{ $booking->reference }}</title>
    <style>
        body { font-family: system-ui, sans-serif; color: #111; margin: 32px; }
        table { border-collapse: collapse; }
        td, th { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
        h1 { margin: 0 0 4px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print"><button onclick="window.print()">Print</button></p>
    <h1>{{ $booking->property?->name }}</h1>
    <p>Guest folio {{ $booking->reference }} · {{ $booking->guest_name }} · {{ $booking->check_in->format('M j') }}–{{ $booking->check_out->format('M j, Y') }}</p>
    @include('folio::partials.ledger', ['voidable' => false])
    <p style="margin-top:24px;color:#666;">Printed {{ now()->format('M j, Y g:i A') }}</p>
</body>
</html>
