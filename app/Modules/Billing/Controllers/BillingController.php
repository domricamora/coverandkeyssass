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

        return view('billing::business.index', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'subscribedIds' => $subscription?->items->pluck('module_id')->all() ?? [],
            'modules' => $modules,
            'entitlements' => TenantModule::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get()->keyBy('module_id'),
            'recurringCents' => $subscription ? $subscription->items->sum(fn ($item) => $this->billing->priceCents($tenant, $item->module, $interval) * $item->quantity) : 0,
            'usage' => Usage::report($tenant),
            'invoices' => Invoice::query()->where('tenant_id', $tenant->id)->latest('id')->limit(24)->get(),
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

        return view('billing::business.invoice', ['invoice' => $this->findInvoice($number)->load('items', 'tenant')]);
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
