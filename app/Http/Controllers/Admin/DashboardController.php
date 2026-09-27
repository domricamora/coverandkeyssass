<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Booking\Models\Booking;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Models\Commission;
use App\Modules\Wallet\Models\Payout;

/**
 * Platform overview (Phase 28): money (guest payments, commission, SaaS),
 * volume (bookings, orders), supply (hosts, listings) and demand
 * (customers), over all time and the last 30 days.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $since = now()->subDays(30);
        $scoped = fn (string $model) => $model::query()->withoutGlobalScope('tenant');
        $paid = fn () => $scoped(Payment::class)->where('status', Payment::PAID);

        return \Inertia\Inertia::render('Admin/Dashboard', [
            'stats' => [
                ['Guest payments', \App\Support\Currency::symbol().number_format((float) $paid()->sum('amount'), 2), \App\Support\Currency::symbol().number_format((float) $paid()->where('paid_at', '>=', $since)->sum('amount'), 2).' last 30 days', route('admin.payments.index')],
                ['Platform commission', \App\Support\Currency::symbol().number_format((float) $scoped(Commission::class)->where('status', '!=', Commission::REVERSED)->sum('platform_fee'), 2), $scoped(Commission::class)->where('status', Commission::PENDING)->count().' pending release', route('admin.commissions.index')],
                ['Subscription revenue', Invoice::money((int) Invoice::query()->where('status', Invoice::PAID)->sum('total_cents')), Invoice::query()->where('status', Invoice::OPEN)->count().' open invoices', route('admin.billing.index')],
                ['Bookings', number_format($scoped(Booking::class)->count()), $scoped(Booking::class)->where('created_at', '>=', $since)->count().' last 30 days', route('admin.bookings.index')],
                ['Food orders', number_format($scoped(Order::class)->count()), $scoped(Order::class)->where('created_at', '>=', $since)->count().' last 30 days', route('admin.orders.index')],
                ['Hosts (businesses)', number_format(Tenant::query()->count()), Tenant::query()->where('status', 'active')->count().' active', route('admin.tenants.index')],
                ['Customers', number_format(User::query()->whereDoesntHave('tenants')->whereDoesntHave('roles', fn ($r) => $r->where('slug', 'super_admin'))->count()), User::query()->where('created_at', '>=', $since)->count().' new users last 30 days', route('admin.users.index', ['type' => 'customers'])],
                ['Properties', number_format($scoped(Property::class)->count()), $scoped(Property::class)->where('status', Property::STATUS_PENDING)->count().' awaiting approval', route('admin.listings.index', 'properties')],
                ['Restaurants', number_format($scoped(Restaurant::class)->count()), $scoped(Restaurant::class)->where('status', Restaurant::STATUS_PENDING)->count().' awaiting approval', route('admin.listings.index', 'restaurants')],
                ['Subscriptions', number_format(Subscription::query()->where('status', '!=', Subscription::CANCELLED)->count()), Subscription::query()->where('status', Subscription::PAST_DUE)->count().' past due', route('admin.billing.index')],
                ['Payouts', number_format($scoped(Payout::class)->where('status', Payout::REQUESTED)->count()).' requested', \App\Support\Currency::symbol().number_format((float) $scoped(Payout::class)->where('status', Payout::REQUESTED)->sum('amount'), 2).' to pay', route('admin.payouts.index')],
            ],
            'recentTenants' => Tenant::query()->latest()->take(6)->get()->map(fn (Tenant $t) => [
                'name' => $t->name, 'type' => ucfirst((string) $t->business_type), 'status' => $t->status, 'href' => route('admin.tenants.show', $t),
            ]),
            'recentAudit' => AuditLog::query()->with('user:id,name')->latest()->take(8)->get()->map(fn (AuditLog $a) => [
                'id' => $a->id, 'action' => $a->action, 'who' => $a->user?->name ?? 'system', 'when' => $a->created_at->diffForHumans(),
            ]),
            'urls' => ['logs' => route('admin.logs.index'), 'tenants' => route('admin.tenants.index')],
        ]);
    }
}
