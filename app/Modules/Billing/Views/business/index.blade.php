<x-app-layout>
    @php($money = fn (int $cents) => \App\Modules\Billing\Models\Invoice::money($cents))
    @php($manage = auth()->user()->hasPermissionTo('billing.manage'))
    <div class="dash-row-head">
        <div>
            <h1>Billing</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">Pay only for the modules {{ $tenant->name }} uses. Prices are VAT-inclusive.</p>
        </div>
    </div>

    @foreach (['success', 'warning'] as $flash)
        @if (session($flash))<div class="card mt-4 p-4" role="status">{{ session($flash) }}</div>@endif
    @endforeach
    @if ($errors->any())
        <div class="card mt-4 p-4" role="alert" style="color:var(--danger, #b91c1c)">{{ $errors->first() }}</div>
    @endif

    @if ($subscription && $subscription->status !== 'cancelled')
        <div class="card mt-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="dash-h2" style="margin-top:0">Your subscription</h2>
                    <p class="mt-1">
                        <span class="badge {{ $subscription->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ str_replace('_', ' ', ucfirst($subscription->status)) }}</span>
                        {{ \App\Modules\Billing\Models\Subscription::INTERVALS[$subscription->billing_interval] }} · {{ $money($recurringCents) }} per {{ $subscription->billing_interval === 'yearly' ? 'year' : 'month' }}
                    </p>
                    <p class="mt-1 text-sm" style="color:var(--text-3)">
                        Current period {{ $subscription->current_period_start->format('M j, Y') }} – {{ $subscription->current_period_end->format('M j, Y') }}.
                        {{ $subscription->cancel_at_period_end ? 'Ends then — it will not renew.' : 'Renews automatically.' }}
                        @if ($subscription->coupon) Coupon <strong>{{ $subscription->coupon->code }}</strong> ({{ $subscription->coupon->label() }}). @endif
                    </p>
                </div>
                @if ($manage)
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('billing.interval') }}" class="flex gap-2">
                            @csrf
                            <select name="interval" class="form-input" aria-label="Billing interval">
                                @foreach (\App\Modules\Billing\Models\Subscription::INTERVALS as $key => $label)
                                    <option value="{{ $key }}" @selected($subscription->billing_interval === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-ghost" type="submit">Switch at renewal</button>
                        </form>
                        <form method="POST" action="{{ route('billing.cancel') }}">
                            @csrf
                            @if ($subscription->cancel_at_period_end)
                                <input type="hidden" name="resume" value="1" />
                                <button class="btn btn-sm btn-dark" type="submit">Keep subscription</button>
                            @else
                                <button class="btn btn-sm btn-ghost" type="submit" onclick="return confirm('End the subscription at the close of this period?')">Cancel subscription</button>
                            @endif
                        </form>
                    </div>
                @endif
            </div>

            @if ($manage && ! $subscription->coupon)
                <form method="POST" action="{{ route('billing.coupon') }}" class="mt-4 flex gap-2" style="max-width:420px">
                    @csrf
                    <input name="coupon" class="form-input" placeholder="Coupon code" aria-label="Coupon code" required />
                    <button class="btn btn-sm btn-ghost" type="submit">Apply</button>
                </form>
            @endif
        </div>
    @endif

    <div class="card mt-6 p-6">
        <h2 class="dash-h2" style="margin-top:0">Modules</h2>
        @if (! $subscription || $subscription->status === 'cancelled')
            <p class="mt-1 text-sm" style="color:var(--text-3)">Choose your modules. Modules with a free trial are only charged from the day the trial ends. Required modules are added for you.</p>
            <form method="POST" action="{{ route('billing.subscribe') }}" class="mt-4">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($modules as $module)
                        @php($entitled = $entitlements[$module->id] ?? null)
                        <label class="rounded-lg border p-4" style="border-color:var(--border);display:block;cursor:pointer">
                            <input type="checkbox" name="modules[]" value="{{ $module->id }}" @checked($entitled && $entitled->status === 'active' || in_array($module->id, old('modules', []))) />
                            <strong style="color:var(--text)">{{ $module->name }}</strong>
                            <span style="display:block;color:var(--text-3);font-size:.9em">{{ $money($module->monthly_cents) }}/month · {{ $money($module->yearly_cents) }}/year</span>
                            @if ($entitled?->isTrialing())<span class="badge badge-amber">Trial until {{ $entitled->trial_ends_at->format('M j') }}</span>@elseif (! $entitled && $module->trial_days)<span class="badge badge-green">{{ $module->trial_days }}-day free trial</span>@endif
                        </label>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap gap-2 items-center">
                    <select name="interval" class="form-input" aria-label="Billing interval" style="max-width:180px">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly (2 months free)</option>
                    </select>
                    <input name="coupon" class="form-input" placeholder="Coupon code (optional)" aria-label="Coupon code" style="max-width:220px" value="{{ old('coupon') }}" />
                    @if ($manage)<button class="btn btn-primary" type="submit">Subscribe</button>@endif
                </div>
            </form>
        @else
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($modules as $module)
                    @php($on = in_array($module->id, $subscribedIds))
                    @php($entitled = $entitlements[$module->id] ?? null)
                    <div class="rounded-lg border p-4" style="border-color: {{ $on ? 'var(--green)' : 'var(--border)' }}">
                        <strong style="color:var(--text)">{{ $module->name }}</strong>
                        <span style="display:block;color:var(--text-3);font-size:.9em">{{ $money($subscription->billing_interval === 'yearly' ? $module->yearly_cents : $module->monthly_cents) }} / {{ $subscription->billing_interval === 'yearly' ? 'year' : 'month' }}</span>
                        @if ($on && $entitled?->expires_at)<span class="badge badge-amber">Suspended — invoice unpaid</span>@elseif ($on && $entitled?->isTrialing())<span class="badge badge-amber">Trial until {{ $entitled->trial_ends_at->format('M j') }}</span>@elseif ($on)<span class="badge badge-green">Subscribed</span>@endif
                        @if ($manage)
                            <div class="mt-3">
                                @if ($on)
                                    <form method="POST" action="{{ route('billing.modules.remove', $module) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-ghost" type="submit" onclick="return confirm('Remove {{ $module->name }}? Its screens switch off now; no credit is given for the rest of the period.')">Remove</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('billing.modules.add') }}">
                                        @csrf
                                        <input type="hidden" name="module_id" value="{{ $module->id }}" />
                                        <button class="btn btn-sm btn-dark" type="submit">Add</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card mt-6 p-6">
        <h2 class="dash-h2" style="margin-top:0">Usage</h2>
        <table class="dash-table mt-3" style="width:100%">
            <thead><tr><th scope="col">Resource</th><th scope="col">Used</th><th scope="col">Plan limit</th></tr></thead>
            <tbody>
                @foreach ($usage as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>{{ $row['used'] }}</td>
                        <td>{{ $row['limit'] ?? 'Unlimited' }} @if ($row['limit'] !== null && $row['used'] >= $row['limit'])<span class="badge badge-amber">At limit</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card mt-6 p-6">
        <h2 class="dash-h2" style="margin-top:0">Invoices</h2>
        @forelse ($invoices as $invoice)
            <div class="flex flex-wrap justify-between gap-2 py-2" style="border-top:1px solid var(--border)">
                <a href="{{ route('billing.invoices.show', $invoice->number) }}"><strong>{{ $invoice->number }}</strong></a>
                <span>{{ $invoice->period_start->format('M j') }} – {{ $invoice->period_end->format('M j, Y') }}</span>
                <span>{{ $money($invoice->total_cents) }}</span>
                <span class="badge {{ $invoice->status === 'paid' ? 'badge-green' : ($invoice->isOverdue() ? 'badge-amber' : '') }}">{{ $invoice->isOverdue() ? 'Overdue' : ucfirst($invoice->status) }}</span>
            </div>
        @empty
            <p style="color:var(--text-3)">No invoices yet.</p>
        @endforelse
    </div>
</x-app-layout>
