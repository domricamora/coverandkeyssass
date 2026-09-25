@php($canManage = auth()->user()->hasPermissionTo('folio.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Folio — {{ $booking->reference }}</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $booking->guest_name }} · {{ $booking->property?->name }} · {{ $booking->check_in->format('M j') }}–{{ $booking->check_out->format('M j, Y') }} · {{ $booking->statusLabel() }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('folio.print', $booking->reference) }}" class="btn btn-ghost" target="_blank" rel="noopener">Print</a>
            <a href="{{ route('bookings.show', $booking->reference) }}" class="btn btn-ghost">Back to booking</a>
        </div>
    </div>

    @foreach (['category', 'description', 'unit_amount', 'amount', 'method', 'reason', 'entry', 'booking'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="card p-6" style="grid-column:span 2">
            @include('folio::partials.ledger', ['voidable' => auth()->user()->hasPermissionTo('folio.void')])
        </div>

        @if ($canManage)
            <div class="space-y-4">
                <form method="POST" action="{{ route('folio.charges.store', $booking->reference) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Post a charge</h2>
                    <select name="category" class="form-input" aria-label="Category">
                        @foreach (\App\Modules\Folio\Models\FolioEntry::MANUAL_CHARGE_CATEGORIES as $category)
                            <option value="{{ $category }}">{{ \Illuminate\Support\Str::headline($category) }}</option>
                        @endforeach
                    </select>
                    <input name="description" type="text" required class="form-input" placeholder="Airport transfer" aria-label="Description" />
                    <div class="flex gap-2">
                        <input name="unit_amount" type="number" step="0.01" min="0.01" required class="form-input" placeholder="Amount ₱" aria-label="Amount" />
                        <input name="quantity" type="number" step="0.01" min="0.01" value="1" class="form-input" style="width:80px" aria-label="Quantity" />
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Post charge</button>
                </form>

                <form method="POST" action="{{ route('folio.payments.store', $booking->reference) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Record a payment</h2>
                    <select name="method" class="form-input" aria-label="Method">
                        @foreach (\App\Modules\Folio\Models\FolioEntry::PAYMENT_METHODS as $method)
                            <option value="{{ $method }}">{{ ucfirst($method) }}</option>
                        @endforeach
                    </select>
                    <input name="amount" type="number" step="0.01" min="0.01" required class="form-input" value="{{ $totals['balance'] > 0 ? $totals['balance'] : '' }}" aria-label="Amount" />
                    <input name="reference" type="text" class="form-input" placeholder="Reference (optional)" aria-label="Reference" />
                    <button type="submit" class="btn btn-primary w-full">Record payment</button>
                </form>

                <form method="POST" action="{{ route('folio.refunds.store', $booking->reference) }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">Refund at the desk</h2>
                    <select name="method" class="form-input" aria-label="Refund method">
                        @foreach (\App\Modules\Folio\Models\FolioEntry::PAYMENT_METHODS as $method)
                            <option value="{{ $method }}">{{ ucfirst($method) }}</option>
                        @endforeach
                    </select>
                    <input name="amount" type="number" step="0.01" min="0.01" required class="form-input" placeholder="Amount ₱" aria-label="Refund amount" />
                    <input name="reason" type="text" class="form-input" placeholder="Reason" aria-label="Refund reason" />
                    <button type="submit" class="btn btn-ghost w-full">Record refund</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
