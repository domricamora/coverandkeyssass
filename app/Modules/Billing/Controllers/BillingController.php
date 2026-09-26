<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\TenantModule;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Support\Usage;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** The business's Billing page (Phase 27): modules, subscription, usage and invoices. Owners only. */
class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request)
    {
        $this->can($request, 'billing.view');
        $tenant = app(TenantContext::class)->tenant();
        $subscription = $this->billing->subscriptionFor($tenant)?->load(['items.module', 'coupon']);
        $interval = $subscription?->billing_interval ?? 'monthly';

        $modules = Module::query()->active()->where('is_core', false)->ordered()->get()
            ->each(function (Module $module) use ($tenant) {
                $module->monthly_cents = $this->billing->priceCents($tenant, $module, 'monthly');
                $module->yearly_cents = $this->billing->priceCents($tenant, $module, 'yearly');
            });

        $live = $subscription && $subscription->status !== Subscription::CANCELLED ? $subscription : null;
        $subscribedIds = $live?->items->pluck('module_id')->all() ?? [];
        $entitlements = TenantModule::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get()->keyBy('module_id');
        $per = $interval === 'yearly' ? 'year' : 'month';

        return Inertia::render('Billing/Index', [
            'business' => $tenant->name,
            'subscription' => $live ? [
                'status' => $live->status,
                'interval' => $live->billing_interval,
                'summary' => Subscription::INTERVALS[$live->billing_interval].' · '.Invoice::money($live->items->sum(fn ($item) => $this->billing->priceCents($tenant, $item->module, $interval) * $item->quantity)).' per '.$per,
                'period' => $live->current_period_start->format('M j, Y').' – '.$live->current_period_end->format('M j, Y'),
                'cancelAtEnd' => (bool) $live->cancel_at_period_end,
                'coupon' => $live->coupon ? $live->coupon->code.' ('.$live->coupon->label().')' : null,
            ] : null,
            'modules' => $modules->map(function (Module $module) use ($entitlements, $subscribedIds, $live, $interval, $per) {
                $entitled = $entitlements[$module->id] ?? null;
                $on = in_array($module->id, $subscribedIds, true);

                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'price' => $live
                        ? Invoice::money($interval === 'yearly' ? $module->yearly_cents : $module->monthly_cents).' / '.$per
                        : Invoice::money($module->monthly_cents).'/month · '.Invoice::money($module->yearly_cents).'/year',
                    'on' => $on,
                    'checked' => $entitled && $entitled->status === 'active',
                    'badge' => match (true) {
                        $on && $entitled?->expires_at !== null => ['warn', 'Suspended — invoice unpaid'],
                        (bool) $entitled?->isTrialing() => ['warn', 'Trial until '.$entitled->trial_ends_at->format('M j')],
                        $on => ['ok', 'Subscribed'],
                        ! $entitled && $module->trial_days > 0 => ['ok', $module->trial_days.'-day free trial'],
                        default => null,
                    },
                    'remove' => route('billing.modules.remove', $module),
                ];
            })->values(),
            'usage' => collect(Usage::report($tenant))->map(fn ($row) => $row + ['atLimit' => $row['limit'] !== null && $row['used'] >= $row['limit']])->values(),
            'invoices' => Invoice::query()->where('tenant_id', $tenant->id)->latest('id')->limit(24)->get()->map(fn (Invoice $i) => [
                'number' => $i->number,
                'period' => $i->period_start->format('M j').' – '.$i->period_end->format('M j, Y'),
                'total' => Invoice::money($i->total_cents),
                'status' => $i->isOverdue() ? 'overdue' : $i->status,
                'href' => route('billing.invoices.show', $i->number),
            ]),
            'intervals' => collect(Subscription::INTERVALS)->map(fn ($label, $key) => [$key, $key === 'yearly' ? 'Yearly (2 months free)' : $label])->values(),
            'can' => ['manage' => $request->user()->hasPermissionTo('billing.manage')],
            'urls' => ['subscribe' => route('billing.subscribe'), 'add' => route('billing.modules.add'), 'interval' => route('billing.interval'), 'coupon' => route('billing.coupon'), 'cancel' => route('billing.cancel')],
        ]);
    }

    public function subscribe(Request $request)
    {
        $this->can($request, 'billing.manage');
        $data = $request->validate([
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['integer'],
            'interval' => ['required', Rule::in(array_keys(Subscription::INTERVALS))],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $this->billing->subscribe(app(TenantContext::class)->tenant(), $data['modules'], $data['interval'], $data['coupon'] ?? null, $request->user());

        return redirect()->route('billing.index')->with('success', 'Subscription started.');
    }

    public function addModule(Request $request)
    {
        $this->can($request, 'billing.manage');
        $data = $request->validate(['module_id' => ['required', 'integer']]);

        $invoice = $this->billing->addModules($this->subscription(), [$data['module_id']]);

        return back()->with('success', 'Module added.'.($invoice && $invoice->total_cents > 0 ? ' Invoice '.$invoice->number.' covers the rest of this period.' : ''));
    }

    public function removeModule(Request $request, Module $module)
    {
        $this->can($request, 'billing.manage');
        $this->billing->removeModule($this->subscription(), $module);

        return back()->with('success', $module->name.' removed from your subscription.');
    }

    public function interval(Request $request)
    {
        $this->can($request, 'billing.manage');
        $data = $request->validate(['interval' => ['required', Rule::in(array_keys(Subscription::INTERVALS))]]);
        $this->billing->changeInterval($this->subscription(), $data['interval']);

        return back()->with('success', 'Billing switches to '.strtolower(Subscription::INTERVALS[$data['interval']]).' at your next renewal.');
    }

    public function coupon(Request $request)
    {
        $this->can($request, 'billing.manage');
        $data = $request->validate(['coupon' => ['required', 'string', 'max:40']]);
        $this->billing->applyCoupon($this->subscription(), $data['coupon']);

        return back()->with('success', 'Coupon applied to your next invoice.');
    }

    public function cancel(Request $request)
    {
        $this->can($request, 'billing.manage');
        $resume = $request->boolean('resume');
        $this->billing->cancel($this->subscription(), ! $resume);

        return back()->with('success', $resume ? 'Your subscription will renew as usual.' : 'Your subscription ends at the close of this period.');
    }

    public function invoice(Request $request, string $number)
    {
        $this->can($request, 'billing.view');

        $invoice = $this->findInvoice($number)->load('items', 'tenant');

        return Inertia::render('Billing/Invoice', [
            'invoice' => [
                'number' => $invoice->number,
                'business' => $invoice->tenant->name,
                'period' => $invoice->period_start->format('M j, Y').' – '.$invoice->period_end->format('M j, Y'),
                'items' => $invoice->items->map(fn ($item) => ['id' => $item->id, 'description' => $item->description, 'quantity' => $item->quantity, 'unit' => Invoice::money($item->unit_cents), 'amount' => Invoice::money($item->amount_cents)]),
                'subtotal' => Invoice::money($invoice->subtotal_cents),
                'discount' => $invoice->discount_cents ? ['code' => $invoice->coupon_code, 'amount' => Invoice::money($invoice->discount_cents)] : null,
                'total' => Invoice::money($invoice->total_cents),
                'status' => $invoice->isOverdue() ? 'overdue' : $invoice->status,
                'paid' => $invoice->status === 'paid' ? trim($invoice->paid_at?->format('M j, Y').($invoice->payment_method && $invoice->payment_method !== 'none' ? ' · '.$invoice->payment_method : '')) : null,
                'due' => $invoice->due_at?->format('M j, Y'),
            ],
            'can' => [
                'pay' => $invoice->status === 'open' && $request->user()->hasPermissionTo('billing.manage'),
                'online' => filled(config('services.paymongo.secret_key')), // billing invoices pay through PayMongo
            ],
            'urls' => ['index' => route('billing.index'), 'pay' => route('billing.invoices.pay', $invoice->number)],
        ]);
    }

    public function pay(Request $request, string $number)
    {
        $this->can($request, 'billing.manage');

        return redirect()->away($this->billing->checkout($this->findInvoice($number)));
    }

    public function paymentReturn(Request $request, string $number)
    {
        $this->can($request, 'billing.view');
        $invoice = $this->billing->sync($this->findInvoice($number));

        return redirect()->route('billing.invoices.show', $invoice->number)
            ->with($invoice->status === Invoice::PAID ? 'success' : 'warning', $invoice->status === Invoice::PAID ? 'Payment received — thank you!' : 'We have not received the payment yet. It can take a minute to confirm.');
    }

    private function subscription(): Subscription
    {
        return $this->billing->subscriptionFor(app(TenantContext::class)->tenant()) ?? abort(404);
    }

    /** Only this business's invoices; anyone else's number is a 404. */
    private function findInvoice(string $number): Invoice
    {
        return Invoice::query()->where('tenant_id', app(TenantContext::class)->id())->where('number', $number)->firstOrFail();
    }

    private function can(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
