<?php

namespace App\Modules\Billing\Services;

use App\Models\Module;
use App\Models\ModulePlan;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionItem;
use App\Modules\Billing\Notifications\BillingNotice;
use App\Modules\Notify\Providers\NotifyServiceProvider;
use App\Modules\Payments\Services\PayMongoGateway;
use App\Support\AuditLogger;
use App\Support\ModuleService;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SaaS billing (Phase 27): a business picks modules (monthly or yearly),
 * pays per module, and gets invoiced each period.
 *
 * - Price: the business's tenant_modules.price_cents override (monthly, set by a
 *   Super Admin), else the module's active plan for the interval.
 * - Trials: a module still in trial is charged only from its trial end,
 *   prorated over the period.
 * - Adding a module mid-period issues a prorated invoice for the rest of it.
 *   ponytail: removing one gives no credit; add credits if customers ask.
 * - Unpaid: past due at due date, modules suspended (tenant_modules.expires_at)
 *   after the grace days, restored the moment the invoice is paid.
 * - Invoices are idempotent by source_key, so a replayed run never bills twice.
 */
class BillingService
{
    public const DUE_DAYS = 7;

    public const GRACE_DAYS = 7;

    public function __construct(
        private readonly ModuleService $modules,
        private readonly AuditLogger $audit,
        private readonly PayMongoGateway $gateway,
    ) {}

    public function subscriptionFor(Tenant $tenant): ?Subscription
    {
        return Subscription::query()->where('tenant_id', $tenant->id)->first();
    }

    /** Monthly or yearly price of a module for this business, in centavos. */
    public function priceCents(Tenant $tenant, Module $module, string $interval): int
    {
        $override = TenantModule::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)->where('module_id', $module->id)->value('price_cents');

        if ($override !== null && $override !== '') {
            return (int) $override * ($interval === 'yearly' ? 10 : 1);
        }

        $plans = ModulePlan::query()->where('module_id', $module->id)->where('is_active', true)->pluck('price_cents', 'billing_interval');

        return (int) ($plans[$interval] ?? ($interval === 'yearly' ? ($plans['monthly'] ?? 0) * 10 : 0));
    }

    /** Billable modules (active, not core) with their non-core dependencies. */
    public function withDependencies(array $moduleIds): Collection
    {
        $modules = Module::query()->active()->where('is_core', false)->whereIn('id', $moduleIds)->get()->keyBy('id');

        do {
            $added = false;
            foreach ($modules as $module) {
                foreach ($this->modules->dependenciesOf($module) as $dependency) {
                    if (! $dependency->is_core && ! $modules->has($dependency->id)) {
                        $modules->put($dependency->id, $dependency);
                        $added = true;
                    }
                }
            }
        } while ($added);

        return $modules->values();
    }

    /** Start (or restart after cancelling) the subscription and bill the first period. */
    public function subscribe(Tenant $tenant, array $moduleIds, string $interval, ?string $couponCode, User $by): Subscription
    {
        $this->validInterval($interval);
        $modules = $this->withDependencies($moduleIds);

        if ($modules->isEmpty()) {
            $this->fail('modules', 'Choose at least one module.');
        }

        $subscription = DB::transaction(function () use ($tenant, $modules, $interval, $couponCode) {
            $subscription = Subscription::query()->where('tenant_id', $tenant->id)->lockForUpdate()->first();

            if ($subscription && $subscription->status !== Subscription::CANCELLED) {
                $this->fail('modules', 'This business already has a subscription. Add modules to it instead.');
            }

            $subscription ??= new Subscription(['tenant_id' => $tenant->id]);
            $subscription->fill([
                'status' => Subscription::ACTIVE,
                'billing_interval' => $interval,
                'current_period_start' => now(),
                'current_period_end' => $this->periodEnd(now(), $interval),
                'cancel_at_period_end' => false,
                'cancelled_at' => null,
                'billing_coupon_id' => null,
                'coupon_cycles_used' => 0,
            ])->save();

            $subscription->items()->delete();
            foreach ($modules as $module) {
                $subscription->items()->create(['module_id' => $module->id]);
            }

            if (filled($couponCode)) {
                $this->redeemCoupon($subscription, $couponCode);
            }

            return $subscription;
        });

        $this->activate($tenant, $modules);
        $this->issue($subscription, 'renewal:'.$subscription->id.':'.$subscription->current_period_start->timestamp, $subscription->current_period_start, $subscription->current_period_end);

        $this->audit->log('billing.subscribed', $subscription, null, ['modules' => $modules->pluck('slug')->all(), 'interval' => $interval], $tenant->id);

        return $subscription->refresh();
    }

    /** Add modules to a running subscription; the rest of this period is invoiced now. */
    public function addModules(Subscription $subscription, array $moduleIds): ?Invoice
    {
        $this->running($subscription);
        $tenant = $subscription->tenant;

        $new = $this->withDependencies($moduleIds)->reject(fn (Module $m) => $subscription->items()->where('module_id', $m->id)->exists());
        if ($new->isEmpty()) {
            $this->fail('module_id', 'Those modules are already on your subscription.');
        }

        foreach ($new as $module) {
            $subscription->items()->firstOrCreate(['module_id' => $module->id]);
        }

        $this->activate($tenant, $new);
        $invoice = $this->issue($subscription, 'change:'.$subscription->id.':'.Str::uuid(), now(), $subscription->current_period_end, $new->pluck('id')->all());

        $this->audit->log('billing.modules_added', $subscription, null, ['modules' => $new->pluck('slug')->all()], $tenant->id);

        return $invoice;
    }

    public function removeModule(Subscription $subscription, Module $module): void
    {
        $this->running($subscription);
        $tenant = $subscription->tenant;

        $item = $subscription->items()->where('module_id', $module->id)->first();
        if (! $item) {
            $this->fail('module_id', 'That module is not on your subscription.');
        }
        if ($subscription->items()->count() === 1) {
            $this->fail('module_id', 'This is your last module — cancel the subscription instead.');
        }

        try {
            $this->modules->disableForTenant($module, $tenant);
        } catch (\RuntimeException $e) {
            $this->fail('module_id', $e->getMessage());
        }

        $item->delete();
        $this->audit->log('billing.module_removed', $subscription, null, ['module' => $module->slug], $tenant->id);
    }

    /** Takes effect at the next renewal. */
    public function changeInterval(Subscription $subscription, string $interval): void
    {
        $this->running($subscription);
        $this->validInterval($interval);
        $subscription->update(['billing_interval' => $interval]);
    }

    public function applyCoupon(Subscription $subscription, string $code): void
    {
        $this->running($subscription);

        DB::transaction(function () use ($subscription, $code) {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if ($locked->billing_coupon_id) {
                $this->fail('coupon', 'A coupon is already applied to this subscription.');
            }
            $this->redeemCoupon($locked, $code);
        });
    }

    public function cancel(Subscription $subscription, bool $cancel = true): void
    {
        $this->running($subscription);
        $subscription->update(['cancel_at_period_end' => $cancel]);
        $this->audit->log($cancel ? 'billing.cancel_requested' : 'billing.resumed', $subscription, null, [], $subscription->tenant_id);
    }

    /**
     * Daily run: renew due periods, end cancelled subscriptions, mark overdue
     * invoices, suspend after grace, and expire unpaid trials.
     *
     * @return array{renewed: int, cancelled: int, past_due: int, suspended: int, trials_expired: int}
     */
    public function run(): array
    {
        $stats = ['renewed' => 0, 'cancelled' => 0, 'past_due' => 0, 'suspended' => 0, 'trials_expired' => 0];

        Subscription::query()->where('status', '!=', Subscription::CANCELLED)->where('current_period_end', '<=', now())->get()
            ->each(function (Subscription $subscription) use (&$stats): void {
                if ($subscription->cancel_at_period_end) {
                    $subscription->update(['status' => Subscription::CANCELLED, 'cancelled_at' => now()]);
                    $this->suspend($subscription);
                    $stats['cancelled']++;

                    return;
                }

                while ($subscription->current_period_end <= now()) {
                    $start = $subscription->current_period_end;
                    $subscription->update(['current_period_start' => $start, 'current_period_end' => $this->periodEnd($start, $subscription->billing_interval)]);
                    $this->issue($subscription, 'renewal:'.$subscription->id.':'.$start->timestamp, $start, $subscription->current_period_end);
                    $stats['renewed']++;
                }
            });

        Invoice::query()->where('status', Invoice::OPEN)->where('due_at', '<=', now())->with('subscription')->get()
            ->each(function (Invoice $invoice) use (&$stats): void {
                $subscription = $invoice->subscription;
                if ($subscription->status === Subscription::ACTIVE) {
                    $subscription->update(['status' => Subscription::PAST_DUE]);
                    $this->notifyOwners($subscription->tenant_id, new BillingNotice('billing_past_due', $invoice,
                        'Invoice '.$invoice->number.' ('.Invoice::money($invoice->total_cents).') is overdue. Pay by '.$invoice->due_at->copy()->addDays(self::GRACE_DAYS)->format('M j').' to keep your modules running.'));
                    $stats['past_due']++;
                }
                if ($invoice->due_at->copy()->addDays(self::GRACE_DAYS)->isPast() && $this->suspend($subscription)) {
                    $stats['suspended']++;
                }
            });

        // Trials that ended on modules nobody pays for (Super Admin grants without a trial never expire).
        $subscribed = SubscriptionItem::query()->join('subscriptions', 'subscriptions.id', '=', 'subscription_items.subscription_id')
            ->where('subscriptions.status', '!=', Subscription::CANCELLED)
            ->get(['subscriptions.tenant_id', 'subscription_items.module_id'])
            ->map(fn ($row) => $row->tenant_id.':'.$row->module_id)->flip();

        TenantModule::query()->withoutGlobalScope('tenant')->with('module')
            ->where('status', 'active')->whereNull('expires_at')->whereNotNull('trial_ends_at')->where('trial_ends_at', '<=', now())
            ->get()
            ->reject(fn (TenantModule $tm) => $tm->module?->is_core || $subscribed->has($tm->tenant_id.':'.$tm->module_id))
            ->each(function (TenantModule $tm) use (&$stats): void {
                $tm->forceFill(['expires_at' => $tm->trial_ends_at])->save();
                $stats['trials_expired']++;
            });

        return $stats;
    }

    /** Record payment (PayMongo, or a Super Admin confirming a bank transfer). */
    public function markPaid(Invoice $invoice, string $method, ?string $reference = null, ?User $by = null): Invoice
    {
        $paid = DB::transaction(function () use ($invoice, $method, $reference, $by) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->status !== Invoice::OPEN) {
                return false;
            }

            $locked->update(['status' => Invoice::PAID, 'paid_at' => now(), 'payment_method' => $method, 'payment_reference' => $reference, 'recorded_by' => $by?->id]);

            return true;
        });

        $invoice->refresh();

        if ($paid) {
            $subscription = $invoice->subscription;
            $stillOverdue = Invoice::query()->where('subscription_id', $subscription->id)->where('status', Invoice::OPEN)->where('due_at', '<=', now())->exists();

            if (! $stillOverdue && $subscription->status !== Subscription::CANCELLED) {
                $subscription->update(['status' => Subscription::ACTIVE]);
                TenantModule::query()->withoutGlobalScope('tenant')->where('tenant_id', $subscription->tenant_id)
                    ->whereIn('module_id', $subscription->items()->pluck('module_id'))
                    ->update(['expires_at' => null]);
            }

            $this->audit->log('billing.invoice_paid', $invoice, null, ['method' => $method, 'total' => $invoice->total_cents], $invoice->tenant_id);
            $this->notifyOwners($invoice->tenant_id, new BillingNotice('billing_invoice', $invoice, 'Thank you — invoice '.$invoice->number.' ('.Invoice::money($invoice->total_cents).') is paid.'));
        }

        return $invoice;
    }

    public function void(Invoice $invoice, User $by): void
    {
        if ($invoice->status !== Invoice::OPEN) {
            $this->fail('invoice', 'Only open invoices can be voided.');
        }

        $invoice->update(['status' => Invoice::VOID, 'recorded_by' => $by->id]);
        $this->audit->log('billing.invoice_voided', $invoice, null, [], $invoice->tenant_id);
    }

    /** Open (or reuse) a PayMongo checkout for an open invoice. */
    public function checkout(Invoice $invoice): string
    {
        if ($invoice->status !== Invoice::OPEN || $invoice->total_cents <= 0) {
            $this->fail('invoice', 'This invoice does not need a payment.');
        }
        if ($invoice->checkout_url) {
            return $invoice->checkout_url;
        }

        try {
            $session = $this->gateway->createCheckoutSession([
                'line_items' => [[
                    'name' => config('app.name').' subscription',
                    'description' => 'Invoice '.$invoice->number,
                    'amount' => $invoice->total_cents,
                    'currency' => $invoice->currency,
                    'quantity' => 1,
                ]],
                'payment_method_types' => config('services.paymongo.methods'),
                'description' => 'Invoice '.$invoice->number,
                'reference_number' => $invoice->number,
                'success_url' => route('billing.invoices.return', $invoice->number),
                'cancel_url' => route('billing.invoices.show', $invoice->number),
                'send_email_receipt' => false,
                'show_line_items' => true,
                'metadata' => ['billing_invoice' => $invoice->number],
            ]);
        } catch (RequestException $e) {
            report($e);
            $this->fail('invoice', 'The payment provider is unavailable right now. Please try again in a moment.');
        }

        $invoice->update(['checkout_session_id' => $session['id'], 'checkout_url' => data_get($session, 'attributes.checkout_url')]);

        return $invoice->checkout_url;
    }

    /** Re-read the checkout from PayMongo and record the payment when it is paid in full. */
    public function sync(Invoice $invoice): Invoice
    {
        if ($invoice->status !== Invoice::OPEN || ! $invoice->checkout_session_id) {
            return $invoice;
        }

        $session = $this->gateway->retrieveCheckoutSession($invoice->checkout_session_id);
        $paid = collect(data_get($session, 'attributes.payments', []))->first(fn ($p) => data_get($p, 'attributes.status') === 'paid');

        if (! $paid || (int) data_get($paid, 'attributes.amount') !== $invoice->total_cents) {
            return $invoice;
        }

        return $this->markPaid($invoice, 'paymongo', (string) data_get($paid, 'id'));
    }

    /**
     * @param  list<int>|null  $onlyModuleIds  bill just these (a mid-period add)
     */
    private function issue(Subscription $subscription, string $key, CarbonInterface $from, CarbonInterface $to, ?array $onlyModuleIds = null): Invoice
    {
        if ($existing = Invoice::query()->where('source_key', $key)->first()) {
            return $existing;
        }

        $tenant = $subscription->tenant;
        $interval = $subscription->billing_interval;
        $periodSeconds = max(1, $subscription->current_period_start->diffInSeconds($subscription->current_period_end));
        $trials = TenantModule::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->pluck('trial_ends_at', 'module_id');

        $lines = [];
        foreach ($subscription->items()->with('module')->get() as $item) {
            if ($onlyModuleIds !== null && ! in_array($item->module_id, $onlyModuleIds, true)) {
                continue;
            }

            $price = $this->priceCents($tenant, $item->module, $interval);
            $trialEnd = isset($trials[$item->module_id]) ? Carbon::parse($trials[$item->module_id]) : null;
            $chargeFrom = $trialEnd && $trialEnd->greaterThan($from) ? $trialEnd : $from;
            $seconds = max(0, $chargeFrom->diffInSeconds($to, false));
            $fraction = min(1, $seconds / $periodSeconds);
            $amount = (int) round($price * $item->quantity * $fraction);

            $description = $item->module->name.' · '.Subscription::INTERVALS[$interval];
            if ($chargeFrom != $from) {
                $description .= $chargeFrom >= $to ? ' · free trial' : ' · from '.$chargeFrom->format('M j').' (trial ends)';
            } elseif ($fraction < 1) {
                $description .= ' · prorated '.$from->format('M j').' – '.$to->format('M j');
            }

            $lines[] = ['module_id' => $item->module_id, 'description' => $description, 'quantity' => $item->quantity, 'unit_cents' => $price, 'amount_cents' => $amount];
        }

        return DB::transaction(function () use ($subscription, $key, $from, $to, $lines) {
            $locked = Subscription::query()->with('coupon')->lockForUpdate()->findOrFail($subscription->id);
            $subtotal = array_sum(array_column($lines, 'amount_cents'));

            $discount = 0;
            $coupon = $locked->coupon;
            if ($coupon && $subtotal > 0 && ($coupon->cycles() === null || $locked->coupon_cycles_used < $coupon->cycles())) {
                $discount = $coupon->discountOn($subtotal);
                $locked->increment('coupon_cycles_used');
            }

            $total = $subtotal - $discount;
            $invoice = Invoice::create([
                'tenant_id' => $locked->tenant_id,
                'subscription_id' => $locked->id,
                'number' => 'CK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'source_key' => $key,
                'period_start' => $from,
                'period_end' => $to,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'total_cents' => $total,
                'coupon_code' => $discount ? $coupon->code : null,
                'due_at' => now()->addDays(self::DUE_DAYS),
            ] + ($total === 0 ? ['status' => Invoice::PAID, 'paid_at' => now(), 'payment_method' => 'none'] : []));

            foreach ($lines as $line) {
                $invoice->items()->create($line);
            }

            DB::afterCommit(function () use ($invoice, $total) {
                if ($total > 0) {
                    $this->notifyOwners($invoice->tenant_id, new BillingNotice('billing_invoice', $invoice,
                        'Invoice '.$invoice->number.' for '.Invoice::money($total).' is ready. Due '.$invoice->due_at->format('M j, Y').'.'));
                }
            });

            return $invoice;
        });
    }

    private function redeemCoupon(Subscription $subscription, string $code): void
    {
        $coupon = Coupon::query()->where('code', Str::upper(trim($code)))->first();

        // Conditional increment: two businesses racing for the last redemption can't both win.
        $won = $coupon && $coupon->usable() && Coupon::query()->whereKey($coupon->id)
            ->where(fn ($q) => $q->whereNull('max_redemptions')->orWhereColumn('redeemed_count', '<', 'max_redemptions'))
            ->increment('redeemed_count') === 1;

        if (! $won) {
            $this->fail('coupon', 'That coupon code is not valid.');
        }

        $subscription->update(['billing_coupon_id' => $coupon->id, 'coupon_cycles_used' => 0]);
    }

    /** Turn the modules on, keeping any trial already running (or used up). */
    private function activate(Tenant $tenant, Collection $modules): void
    {
        foreach ($modules as $module) {
            $existing = TenantModule::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('module_id', $module->id)->first();

            if ($existing) {
                $existing->forceFill(['status' => 'active', 'expires_at' => null])->save();
            } else {
                $this->modules->enableForTenant($module, $tenant);
            }
        }
    }

    /** @return bool whether anything was newly suspended */
    private function suspend(Subscription $subscription): bool
    {
        return TenantModule::query()->withoutGlobalScope('tenant')->where('tenant_id', $subscription->tenant_id)
            ->whereIn('module_id', $subscription->items()->pluck('module_id'))
            ->whereNull('expires_at')
            ->update(['expires_at' => now()]) > 0;
    }

    private function notifyOwners(int $tenantId, BillingNotice $notice): void
    {
        NotifyServiceProvider::staffWith($tenantId, 'billing.manage')->each(fn (User $user) => $user->notify($notice));
    }

    private function periodEnd(CarbonInterface $start, string $interval): CarbonInterface
    {
        return $interval === 'yearly' ? $start->copy()->addYearNoOverflow() : $start->copy()->addMonthNoOverflow();
    }

    private function running(Subscription $subscription): void
    {
        if ($subscription->status === Subscription::CANCELLED) {
            $this->fail('subscription', 'This subscription has ended. Subscribe again to continue.');
        }
    }

    private function validInterval(string $interval): void
    {
        if (! isset(Subscription::INTERVALS[$interval])) {
            $this->fail('interval', 'Choose monthly or yearly billing.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
