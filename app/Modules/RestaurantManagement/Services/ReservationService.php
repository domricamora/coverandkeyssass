<?php

namespace App\Modules\RestaurantManagement\Services;

use App\Models\User;
use App\Modules\Marketplace\Models\Restaurant;
use App\Modules\RestaurantManagement\Models\TableReservation;
use App\Modules\RestaurantManagement\Notifications\ReservationStatusChanged;
use App\Support\AuditLogger;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Table reservations (Phase 10). Every reservation and state change goes
 * through here.
 *
 * Overbooking: reserve() locks the restaurant's active tables
 * (SELECT … FOR UPDATE) before looking for a free one, so concurrent
 * requests for the same restaurant queue up and each re-reads the overlap
 * after the previous one commits. Time ranges cannot carry a unique index,
 * so the lock is the guarantee.
 */
class ReservationService
{
    // ponytail: fixed 30-minute slot grid; make it a restaurant column if hosts ask.
    public const SLOT_MINUTES = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Bookable start times ("18:30") for a date, from the day's opening
     * hours ("11:00–14:00, 17:00–22:00"). The last slot leaves a full
     * sitting before closing. A missing or "Closed" day has no slots.
     *
     * @return list<string>
     */
    public function slotsFor(Restaurant $restaurant, CarbonImmutable $date): array
    {
        $hours = (string) (($restaurant->opening_hours ?? [])[strtolower($date->englishDayOfWeek)] ?? '');
        $duration = $this->duration($restaurant);
        $slots = [];

        preg_match_all('/(\d{1,2}):(\d{2})\s*[–—-]\s*(\d{1,2}):(\d{2})/u', $hours, $ranges, PREG_SET_ORDER);

        foreach ($ranges as [, $oh, $om, $ch, $cm]) {
            $open = (int) $oh * 60 + (int) $om;
            $close = (int) $ch * 60 + (int) $cm;
            if ($close <= $open) {
                $close += 24 * 60; // past midnight
            }

            for ($t = $open; $t + $duration <= $close; $t += self::SLOT_MINUTES) {
                $slots[] = sprintf('%02d:%02d', intdiv($t, 60) % 24, $t % 60);
            }
        }

        return array_values(array_unique($slots));
    }

    /**
     * @param  array{date: string, time: string, party_size: int|string, guest_name: string, guest_email?: ?string,
     *               guest_phone?: ?string, special_requests?: ?string, restaurant_table_id?: int|string|null}  $data
     */
    public function reserve(Restaurant $restaurant, array $data, string $source, ?User $customer = null, ?User $actor = null): TableReservation
    {
        $start = CarbonImmutable::parse($data['date'].' '.$data['time']);
        $end = $start->addMinutes($this->duration($restaurant));
        $party = (int) $data['party_size'];
        $tableId = $data['restaurant_table_id'] ?? null;

        if ($start->isPast()) {
            $this->fail('time', 'Pick a time in the future.');
        }

        if ($source === TableReservation::SOURCE_MARKETPLACE && ! in_array($start->format('H:i'), $this->slotsFor($restaurant, $start), true)) {
            $this->fail('time', 'That time is not bookable. Choose one of the listed slots.');
        }

        $status = $source === TableReservation::SOURCE_HOST ? TableReservation::CONFIRMED : TableReservation::PENDING;

        $reservation = DB::transaction(function () use ($restaurant, $data, $source, $customer, $actor, $start, $end, $party, $tableId, $status) {
            // Serialise concurrent reservations of this restaurant.
            $tables = $restaurant->tables()->active()->lockForUpdate()->get();

            if ($tables->isEmpty() || $tables->max('seats') < $party) {
                $this->fail('party_size', $tables->isEmpty()
                    ? 'This restaurant has no tables set up for reservations.'
                    : 'The largest table seats '.$tables->max('seats').'. Please call the restaurant for bigger groups.');
            }

            $taken = TableReservation::query()
                ->where('restaurant_id', $restaurant->getKey())
                ->blocking()
                ->overlapping($start, $end)
                ->pluck('restaurant_table_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $table = $tables
                ->filter(fn ($t) => $t->seats >= $party && ! in_array((int) $t->id, $taken, true))
                ->when($tableId, fn ($c) => $c->where('id', (int) $tableId))
                ->sortBy([['seats', 'asc'], ['label', 'asc']])
                ->first();

            if (! $table) {
                $this->fail('time', $tableId
                    ? 'That table is taken or too small at this time.'
                    : 'No table for '.$party.' is free at '.$start->format('g:i A').'. Try another time.');
            }

            $reservation = TableReservation::create([
                'restaurant_id' => $restaurant->getKey(),
                'restaurant_table_id' => $table->id,
                'user_id' => $customer?->getKey(),
                'created_by' => $actor?->getKey(),
                'reference' => TableReservation::newReference(),
                'source' => $source,
                'status' => $status,
                'reserved_at' => $start,
                'ends_at' => $end,
                'party_size' => $party,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'] ?? $customer?->email,
                'guest_phone' => $data['guest_phone'] ?? null,
                'special_requests' => $data['special_requests'] ?? null,
            ]);

            if ($status === TableReservation::CONFIRMED) {
                $reservation->forceFill(['confirmed_at' => now()])->save();
            }

            return $reservation;
        });

        $this->audit->log('reservation.created', $reservation, null, [
            'reference' => $reservation->reference,
            'source' => $source,
            'table' => $reservation->restaurant_table_id,
            'reserved_at' => $reservation->reserved_at->toDateTimeString(),
        ]);

        return $reservation;
    }

    public function transition(TableReservation $reservation, string $to, ?string $reason = null): TableReservation
    {
        if (! $reservation->canTransitionTo($to)) {
            $this->fail('status', 'A '.strtolower($reservation->statusLabel()).' reservation cannot be marked '.str_replace('_', ' ', $to).'.');
        }

        if ($to === TableReservation::SEATED && ! $reservation->reserved_at->isToday()) {
            $this->fail('status', 'Guests can only be seated on the day of the reservation.');
        }

        if ($to === TableReservation::NO_SHOW && $reservation->reserved_at->isFuture()) {
            $this->fail('status', 'A no-show can only be recorded after the reserved time.');
        }

        $from = $reservation->status;

        $reservation->status = $to;
        match ($to) {
            TableReservation::CONFIRMED => $reservation->forceFill(['confirmed_at' => now()]),
            TableReservation::SEATED => $reservation->forceFill(['seated_at' => now()]),
            TableReservation::CANCELLED => $reservation->forceFill(['cancelled_at' => now(), 'cancellation_reason' => $reason]),
            // Free the rest of the sitting once the party leaves.
            TableReservation::COMPLETED => $reservation->forceFill(['ends_at' => now()->min($reservation->ends_at)]),
            default => null,
        };
        $reservation->save();

        $this->audit->log('reservation.'.$to, $reservation, ['status' => $from], ['status' => $to, 'reason' => $reason]);

        if (in_array($to, [TableReservation::CONFIRMED, TableReservation::CANCELLED], true)) {
            $reservation->customer?->notify(new ReservationStatusChanged($reservation));
        }

        return $reservation;
    }

    /** Run a callback inside the tenant that owns $model (marketplace / customer portal). */
    public function asTenantOf(Model $model, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAs($model, $callback);
    }

    private function duration(Restaurant $restaurant): int
    {
        return max(15, (int) ($restaurant->reservation_duration_minutes ?: 90));
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
