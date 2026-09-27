<?php

namespace App\Modules\PlatformAdmin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Booking\Models\Booking;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Models\Commission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Super Admin (Phase 28): CSV exports and the audit log viewer. */
class ReportController extends Controller
{
    /** report => [label, query builder, header => row value] */
    private function reports(): array
    {
        return [
            'bookings' => ['Bookings', fn () => Booking::query()->withoutGlobalScope('tenant')->with('tenant'), [
                'Reference' => fn ($b) => $b->reference, 'Business' => fn ($b) => $b->tenant?->name, 'Status' => fn ($b) => $b->status,
                'Guest' => fn ($b) => $b->guest_name, 'Check-in' => fn ($b) => $b->check_in?->toDateString(), 'Check-out' => fn ($b) => $b->check_out?->toDateString(),
                'Total' => fn ($b) => $b->total, 'Currency' => fn ($b) => $b->currency, 'Created' => fn ($b) => $b->created_at->toDateTimeString(),
            ]],
            'orders' => ['Food orders', fn () => Order::query()->withoutGlobalScope('tenant'), [
                'Reference' => fn ($o) => $o->reference, 'Business ID' => fn ($o) => $o->tenant_id, 'Status' => fn ($o) => $o->status,
                'Channel' => fn ($o) => $o->channel, 'Fulfillment' => fn ($o) => $o->fulfillment, 'Payment' => fn ($o) => $o->payment_method.' / '.$o->payment_status,
                'Total' => fn ($o) => $o->total, 'Created' => fn ($o) => $o->created_at->toDateTimeString(),
            ]],
            'payments' => ['Online payments', fn () => Payment::query()->withoutGlobalScope('tenant'), [
                'ID' => fn ($p) => $p->id, 'Business ID' => fn ($p) => $p->tenant_id, 'Booking ID' => fn ($p) => $p->booking_id, 'Order ID' => fn ($p) => $p->order_id,
                'Status' => fn ($p) => $p->status, 'Method' => fn ($p) => $p->method, 'Amount' => fn ($p) => $p->amount, 'Refunded' => fn ($p) => $p->refunded_amount,
                'Paid at' => fn ($p) => $p->paid_at?->toDateTimeString(), 'Created' => fn ($p) => $p->created_at->toDateTimeString(),
            ]],
            'commissions' => ['Commissions', fn () => Commission::query()->withoutGlobalScope('tenant'), [
                'ID' => fn ($c) => $c->id, 'Business ID' => fn ($c) => $c->tenant_id, 'Status' => fn ($c) => $c->status, 'Gross' => fn ($c) => $c->gross,
                'Rate %' => fn ($c) => $c->rate, 'Platform fee' => fn ($c) => $c->platform_fee, 'Host amount' => fn ($c) => $c->host_amount, 'Created' => fn ($c) => $c->created_at->toDateTimeString(),
            ]],
            'subscriptions' => ['Subscription invoices', fn () => Invoice::query()->with('tenant'), [
                'Number' => fn ($i) => $i->number, 'Business' => fn ($i) => $i->tenant?->name, 'Status' => fn ($i) => $i->status,
                'Period start' => fn ($i) => $i->period_start?->toDateString(), 'Period end' => fn ($i) => $i->period_end?->toDateString(),
                'Subtotal' => fn ($i) => $i->subtotal_cents / 100, 'Discount' => fn ($i) => $i->discount_cents / 100, 'Total' => fn ($i) => $i->total_cents / 100,
                'Paid at' => fn ($i) => $i->paid_at?->toDateTimeString(), 'Created' => fn ($i) => $i->created_at->toDateTimeString(),
            ]],
        ];
    }

    public function index()
    {
        return \Inertia\Inertia::render('Admin/Reports', [
            'reports' => collect($this->reports())->map(fn ($r, $key) => ['key' => $key, 'label' => $r[0], 'url' => route('admin.reports.export', $key)])->values(),
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        $reports = $this->reports();
        abort_unless(isset($reports[$report]), 404);
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

        [, $query, $columns] = $reports[$report];
        $query = $query()->whereBetween('created_at', [$data['from'].' 00:00:00', $data['to'].' 23:59:59'])->orderBy('id');

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_keys($columns));
            $query->chunk(500, function ($rows) use ($out, $columns) {
                foreach ($rows as $row) {
                    // Leading =, +, -, @ would run as a formula in spreadsheets.
                    fputcsv($out, array_map(fn ($get) => preg_replace('/^([=+\-@])/', "'$1", (string) $get($row)), $columns));
                }
            });
            fclose($out);
        }, $report.'-'.$data['from'].'-to-'.$data['to'].'.csv', ['Content-Type' => 'text/csv']);
    }

    public function logs(Request $request)
    {
        $logs = AuditLog::query()->with(['user:id,name,email', 'tenant:id,name'])
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($request->query('user'), fn ($q, $u) => $q->whereHas('user', fn ($w) => $w->where('email', 'like', "%{$u}%")))
            ->when($request->query('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->latest('id')->paginate(50)->withQueryString();

        return \Inertia\Inertia::render('Admin/Logs', [
            'logs' => $logs->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'when' => $l->created_at->format('M j, Y g:i A'),
                'action' => $l->action,
                'who' => $l->user?->name ?? 'system',
                'ip' => $l->ip_address,
                'business' => $l->tenant?->name,
                'details' => \Illuminate\Support\Str::limit(json_encode($l->new_values ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200),
            ]),
            'filters' => $request->only('action', 'user', 'tenant', 'from'),
            'prefixes' => AuditLog::query()->selectRaw("substring_index(action, '.', 1) as p")->distinct()->orderBy('p')->pluck('p'),
        ]);
    }
}
