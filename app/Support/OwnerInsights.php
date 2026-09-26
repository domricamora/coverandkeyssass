<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owner overview numbers for one or more businesses (tenant ids): hotel
 * KPIs (occupancy, ADR, RevPAR), restaurant revenue, daily series, channel
 * mix, cancellations, top dishes and labour. Plain aggregate queries,
 * always filtered by tenant_id — callers pass only businesses the user
 * belongs to. Revenue = posted room charges (folio) + completed orders.
 */
class OwnerInsights
{
    /** @param list<int> $tenantIds */
    public function __construct(private readonly array $tenantIds) {}

    /** @return array<string, mixed> KPIs for [from, to] (inclusive dates). */
    public function kpis(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $rooms = (int) DB::table('rooms')->whereIn('tenant_id', $this->tenantIds)->whereNull('deleted_at')->where('status', 'active')->count();
        $available = max(1, $rooms * $days);

        $nightsSold = (int) DB::table('room_nights')->join('rooms', 'rooms.id', '=', 'room_nights.room_id')
            ->whereIn('rooms.tenant_id', $this->tenantIds)->whereBetween('room_nights.night', [$from->toDateString(), $to->toDateString()])->count();

        $roomRevenue = (float) $this->roomCharges($from, $to)->sum('amount');
        $fnb = $this->completedOrders($from, $to);
        $fnbRevenue = (float) (clone $fnb)->sum('total');
        $orders = (int) (clone $fnb)->count();

        $bookings = DB::table('bookings')->whereIn('tenant_id', $this->tenantIds)->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
        $made = (int) (clone $bookings)->count();
        $lost = (int) (clone $bookings)->whereIn('status', ['cancelled', 'no_show'])->count();

        return [
            'occupancy' => round($nightsSold / $available * 100, 1),
            'adr' => $nightsSold ? round($roomRevenue / $nightsSold, 2) : 0.0,
            'revpar' => round($roomRevenue / $available, 2),
            'room_revenue' => round($roomRevenue, 2),
            'fnb_revenue' => round($fnbRevenue, 2),
            'revenue' => round($roomRevenue + $fnbRevenue, 2),
            'nights_sold' => $nightsSold,
            'orders' => $orders,
            'avg_ticket' => $orders ? round($fnbRevenue / $orders, 2) : 0.0,
            'bookings' => $made,
            'cancellation_rate' => $made ? round($lost / $made * 100, 1) : 0.0,
            'labour_hours' => round((int) DB::table('attendances')->whereIn('tenant_id', $this->tenantIds)->whereBetween('clock_in_at', [$from->startOfDay(), $to->endOfDay()])->sum('minutes_worked') / 60, 1),
            'late_clock_ins' => (int) DB::table('attendances')->whereIn('tenant_id', $this->tenantIds)->whereBetween('clock_in_at', [$from->startOfDay(), $to->endOfDay()])->where('late_minutes', '>', 5)->count(),
            'rooms' => $rooms,
        ];
    }

    /** @return list<array{date: string, rooms: float, fnb: float, occupancy: float}> */
    public function daily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rooms = max(1, (int) DB::table('rooms')->whereIn('tenant_id', $this->tenantIds)->whereNull('deleted_at')->where('status', 'active')->count());
        $roomRev = $this->roomCharges($from, $to)->selectRaw('service_date d, sum(amount) v')->groupBy('service_date')->pluck('v', 'd');
        $fnbRev = $this->completedOrders($from, $to)->selectRaw('date(created_at) d, sum(total) v')->groupByRaw('date(created_at)')->pluck('v', 'd');
        $sold = DB::table('room_nights')->join('rooms', 'rooms.id', '=', 'room_nights.room_id')
            ->whereIn('rooms.tenant_id', $this->tenantIds)->whereBetween('room_nights.night', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('room_nights.night d, count(*) v')->groupBy('room_nights.night')->pluck('v', 'd');

        $series = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $d = $day->toDateString();
            $series[] = [
                'date' => $d,
                'rooms' => round((float) ($roomRev[$d] ?? 0), 2),
                'fnb' => round((float) ($fnbRev[$d] ?? 0), 2),
                'occupancy' => round((int) ($sold[$d] ?? 0) / $rooms * 100, 1),
            ];
        }

        return $series;
    }

    /** @return array<string, int> bookings by source (check-in in range). */
    public function channels(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('bookings')->whereIn('tenant_id', $this->tenantIds)->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('source, count(*) c')->groupBy('source')->pluck('c', 'source')->map(fn ($c) => (int) $c)->all();
    }

    /** @return list<array{name: string, qty: int, revenue: float}> */
    public function topDishes(CarbonImmutable $from, CarbonImmutable $to, int $limit = 6): array
    {
        return DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.tenant_id', $this->tenantIds)->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('order_items.name, sum(order_items.quantity) qty, sum(order_items.line_total) revenue')
            ->groupBy('order_items.name')->orderByDesc('revenue')->limit($limit)->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => round((float) $r->revenue, 2)])->all();
    }

    /** Right now: the operational pulse. */
    public function today(): array
    {
        $date = today()->toDateString();
        $bookings = fn () => DB::table('bookings')->whereIn('tenant_id', $this->tenantIds);

        return [
            'arrivals' => (int) $bookings()->where('check_in', $date)->whereIn('status', ['pending', 'held', 'confirmed'])->count(),
            'in_house' => (int) $bookings()->where('status', 'checked_in')->count(),
            'departures' => (int) $bookings()->where('check_out', $date)->where('status', 'checked_in')->count(),
            'dirty' => (int) DB::table('rooms')->whereIn('tenant_id', $this->tenantIds)->whereNull('deleted_at')->where('housekeeping_status', 'dirty')->count(),
            'maintenance' => (int) DB::table('maintenance_tickets')->whereIn('tenant_id', $this->tenantIds)->whereIn('status', ['open', 'in_progress', 'on_hold'])->count(),
            'open_tickets' => (int) DB::table('orders')->whereIn('tenant_id', $this->tenantIds)->where('channel', 'pos')->whereIn('status', ['accepted', 'preparing', 'ready'])->count(),
            'on_shift' => (int) DB::table('shifts')->whereIn('tenant_id', $this->tenantIds)->where('status', 'scheduled')->where('starts_at', '<=', now())->where('ends_at', '>', now())->count(),
        ];
    }

    private function roomCharges(CarbonImmutable $from, CarbonImmutable $to)
    {
        return DB::table('folio_entries')->whereIn('tenant_id', $this->tenantIds)->where('type', 'charge')->where('category', 'room')
            ->whereNull('voided_at')->whereBetween('service_date', [$from->toDateString(), $to->toDateString()]);
    }

    private function completedOrders(CarbonImmutable $from, CarbonImmutable $to)
    {
        return DB::table('orders')->whereIn('tenant_id', $this->tenantIds)->where('status', 'completed')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
    }
}
