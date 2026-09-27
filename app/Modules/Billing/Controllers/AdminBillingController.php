<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Super Admin billing (Phase 27): subscriptions, invoices (confirm bank transfers, void) and coupons. */
class AdminBillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request)
    {
        $subscriptions = Subscription::query()->with(['tenant', 'items.module'])->latest('id')->get();

        $status = in_array($request->query('status'), ['open', 'paid', 'void', 'all'], true) ? $request->query('status') : 'open';
        $mrrCents = $subscriptions->where('status', '!=', Subscription::CANCELLED)->sum(fn (Subscription $s) => $s->items->sum(
            fn ($item) => intdiv($this->billing->priceCents($s->tenant, $item->module, $s->billing_interval) * $item->quantity, $s->billing_interval === 'yearly' ? 12 : 1)));

        return \Inertia\Inertia::render('Admin/Billing', [
            'summary' => ['live' => $subscriptions->where('status', '!=', Subscription::CANCELLED)->count(), 'mrr' => Invoice::money($mrrCents)],
            'status' => $status,
            'invoices' => Invoice::query()->with('tenant')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)->withQueryString()
                ->through(fn (Invoice $i) => [
                    'id' => $i->id,
                    'number' => $i->number,
                    'business' => $i->tenant?->name,
                    'total' => Invoice::money($i->total_cents),
                    'due' => $i->due_at?->format('M j, Y'),
                    'status' => $i->isOverdue() ? 'overdue' : $i->status,
                    'reference' => $i->payment_reference,
                    'open' => $i->status === Invoice::OPEN,
                    'paid' => route('admin.billing.invoices.paid', $i),
                    'void' => route('admin.billing.invoices.void', $i),
                ]),
            'subscriptions' => $subscriptions->map(fn (Subscription $s) => [
                'id' => $s->id,
                'business' => $s->tenant?->name,
                'href' => $s->tenant ? route('admin.tenants.show', $s->tenant) : null,
                'modules' => $s->items->map(fn ($i) => $i->module?->name)->filter()->implode(', '),
                'interval' => ucfirst((string) $s->billing_interval),
                'ends' => $s->current_period_end?->format('M j, Y').($s->cancel_at_period_end ? ' (cancels)' : ''),
                'status' => $s->status,
            ]),
            'coupons' => Coupon::query()->latest('id')->get()->map(fn (Coupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'label' => $c->label(),
                'used' => $c->redeemed_count.($c->max_redemptions ? ' / '.$c->max_redemptions : '').' used'.($c->expires_at ? ' · expires '.$c->expires_at->format('M j, Y') : ''),
                'active' => (bool) $c->is_active,
                'toggle' => route('admin.billing.coupons.toggle', $c),
            ]),
            'durations' => Coupon::DURATIONS,
            'symbol' => \App\Support\Currency::platform(),
            'urls' => ['coupons' => route('admin.billing.coupons.store')],
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:120']]);
        abort_unless($invoice->status === Invoice::OPEN, 422, 'This invoice is not open.');

        $this->billing->markPaid($invoice, 'manual', $data['reference'], $request->user());

        return back()->with('success', 'Invoice '.$invoice->number.' marked paid.');
    }

    public function void(Request $request, Invoice $invoice)
    {
        $this->billing->void($invoice, $request->user());

        return back()->with('success', 'Invoice '.$invoice->number.' voided.');
    }

    public function storeCoupon(Request $request)
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', 'unique:billing_coupons,code'],
            'name' => ['required', 'string', 'max:120'],
            'percent_off' => ['nullable', 'integer', 'between:1,100', 'required_without:amount_off', 'prohibits:amount_off'],
            'amount_off' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
            'duration' => ['required', Rule::in(array_keys(Coupon::DURATIONS))],
            'duration_cycles' => ['nullable', 'integer', 'between:1,36', 'required_if:duration,repeating'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        Coupon::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'percent_off' => $data['percent_off'] ?? null,
            'amount_off_cents' => isset($data['amount_off']) ? (int) round($data['amount_off'] * 100) : null,
            'duration' => $data['duration'],
            'duration_cycles' => $data['duration'] === 'repeating' ? $data['duration_cycles'] : null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return back()->with('success', 'Coupon '.$data['code'].' created.');
    }

    public function toggleCoupon(Coupon $coupon)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', 'Coupon '.$coupon->code.($coupon->is_active ? ' enabled.' : ' disabled.'));
    }
}
