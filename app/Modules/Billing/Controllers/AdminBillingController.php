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

        return view('billing::admin.index', [
            'subscriptions' => $subscriptions,
            'mrrCents' => $subscriptions->where('status', '!=', Subscription::CANCELLED)->sum(fn (Subscription $s) => $s->items->sum(
                fn ($item) => intdiv($this->billing->priceCents($s->tenant, $item->module, $s->billing_interval) * $item->quantity, $s->billing_interval === 'yearly' ? 12 : 1))),
            'invoices' => Invoice::query()->with('tenant')
                ->when($request->query('status', 'open') !== 'all', fn ($q) => $q->where('status', $request->query('status', 'open')))
                ->latest('id')->paginate(30)->withQueryString(),
            'coupons' => Coupon::query()->latest('id')->get(),
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
