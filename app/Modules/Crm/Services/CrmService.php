<?php

namespace App\Modules\Crm\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Models\Interaction;
use App\Modules\Crm\Models\Tag;
use App\Modules\Ordering\Models\Order;
use App\Modules\RestaurantManagement\Models\TableReservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRM (Phase 21). sync() folds every guest the business has seen —
 * bookings, food orders, table reservations — into one contact each,
 * matched by linked account, then email, then phone, and refreshes the
 * cached metrics segments run on. Idempotent; runs on the CRM screens.
 *
 * Runs inside the active tenant.
 */
class CrmService
{
    public const STAY_STATUSES = [Booking::CHECKED_IN, Booking::CHECKED_OUT, Booking::COMPLETED];

    /**
     * Fold bookings, orders and table reservations into contacts.
     *
     * Incremental: only sources changed since this business's last sync (a
     * cache watermark) are folded, and only the contacts they touched get
     * their metrics refreshed. No watermark (first run, cache cleared) or
     * $full means a complete pass, so a lost cache costs time, not data.
     */
    public function sync(bool $full = false): void
    {
        $key = 'crm.synced_at.'.(app(\App\Support\TenantContext::class)->id() ?? 0);
        // A plain timestamp string: cache stores that serialise (database, redis)
        // only restore whitelisted classes, so a stored Carbon came back as
        // __PHP_Incomplete_Class. Anything but a string means "full pass".
        $cached = $full ? null : \Illuminate\Support\Facades\Cache::get($key);
        $since = is_string($cached) ? $cached : null;
        $startedAt = now();
        $recent = fn ($query) => $query->when($since, fn ($q) => $q->where('updated_at', '>=', $since));
        $touched = [];

        $recent(Booking::query()->select(['id', 'user_id', 'guest_name', 'guest_email', 'guest_phone', 'updated_at']))->each(
            function ($b) use (&$touched) { $touched[] = $this->upsert($b->user_id, $b->guest_name, $b->guest_email, $b->guest_phone, 'booking')?->id; });

        $recent(Order::query()->with('customer:id,email')->select(['id', 'user_id', 'customer_name', 'customer_phone', 'updated_at']))->each(
            function ($o) use (&$touched) { $touched[] = $this->upsert($o->user_id, $o->customer_name, $o->customer?->email, $o->customer_phone, 'order')?->id; });

        $recent(TableReservation::query()->select(['id', 'user_id', 'guest_name', 'guest_email', 'guest_phone', 'updated_at']))->each(
            function ($r) use (&$touched) { $touched[] = $this->upsert($r->user_id, $r->guest_name, $r->guest_email, $r->guest_phone, 'reservation')?->id; });

        Contact::query()
            ->when($since, fn ($q) => $q->whereIn('id', array_unique(array_filter($touched))))
            ->each(fn (Contact $c) => $this->refreshMetrics($c));

        // A little overlap so rows written during this run are seen next time.
        \Illuminate\Support\Facades\Cache::forever($key, $startedAt->subSeconds(5)->toDateTimeString());
    }

    /** Find the contact for an identity (account → email → phone) or create it; fill in what was missing. */
    public function upsert(?int $userId, ?string $name, ?string $email, ?string $phone, string $source): ?Contact
    {
        $email = $email ? mb_strtolower(trim($email)) : null;
        $phone = self::normalizePhone($phone);

        if (! $userId && ! $email && ! $phone) {
            return null; // walk-ins with no contact details stay anonymous
        }

        $contact = ($userId ? Contact::query()->where('user_id', $userId)->first() : null)
            ?? ($email ? Contact::query()->where('email', $email)->first() : null)
            ?? ($phone ? Contact::query()->where('phone', $phone)->first() : null);

        if (! $contact) {
            return Contact::create(['user_id' => $userId, 'name' => $name ?: ($email ?? $phone), 'email' => $email, 'phone' => $phone, 'source' => $source]);
        }

        $fill = array_filter([
            'user_id' => $contact->user_id ? null : ($userId && ! Contact::query()->where('user_id', $userId)->exists() ? $userId : null),
            'email' => $contact->email ? null : ($email && ! Contact::query()->where('email', $email)->exists() ? $email : null),
            'phone' => $contact->phone ? null : $phone,
        ]);

        if ($fill !== []) {
            $contact->update($fill);
        }

        return $contact;
    }

    public function refreshMetrics(Contact $contact): Contact
    {
        $stays = $contact->bookingsQuery()->whereIn('status', self::STAY_STATUSES);
        $orders = $contact->ordersQuery()->where('status', Order::COMPLETED);
        $tables = $contact->reservationsQuery()->whereIn('status', [TableReservation::SEATED, TableReservation::COMPLETED]);

        $firstSeen = collect([
            $contact->bookingsQuery()->min('created_at'),
            $contact->ordersQuery()->min('created_at'),
            $contact->reservationsQuery()->min('created_at'),
        ])->filter()->min();

        $lastActivity = collect([
            (clone $stays)->max('check_out'),
            (clone $orders)->max('completed_at'),
            (clone $tables)->max('reserved_at'),
        ])->filter()->max();

        $contact->forceFill([
            'bookings_count' => (clone $stays)->count(),
            'orders_count' => (clone $orders)->count(),
            'reservations_count' => (clone $tables)->count(),
            'total_spend' => round((float) (clone $stays)->sum('total') + (float) (clone $orders)->sum('total'), 2),
            'first_seen_at' => $firstSeen ? Carbon::parse($firstSeen) : ($contact->first_seen_at ?? $contact->created_at),
            'last_activity_at' => $lastActivity ? Carbon::parse($lastActivity) : $contact->last_activity_at,
        ])->save();

        return $contact;
    }

    /** @param list<string> $names */
    public function setTags(Contact $contact, array $names): void
    {
        $ids = collect($names)->map(fn ($n) => trim((string) $n))->filter()->unique(fn ($n) => mb_strtolower($n))
            ->map(fn ($n) => Tag::query()->firstOrCreate(['name' => mb_substr($n, 0, 60)])->id);

        $contact->tags()->sync($ids->all());
    }

    public function setConsent(Contact $contact, bool $consent): void
    {
        $contact->forceFill(['marketing_consent' => $consent, 'consent_at' => $consent ? now() : null])->save();
    }

    public function note(Contact $contact, string $body, User $by): void
    {
        $contact->notes()->create(['user_id' => $by->id, 'body' => $body]);
    }

    /** Log a conversation. System senders pass a source key so a retry never logs twice. */
    public function log(Contact $contact, string $channel, string $direction, ?string $subject, ?string $body, ?User $by = null, ?string $sourceKey = null, ?string $at = null): Interaction
    {
        if (! in_array($channel, Interaction::CHANNELS, true)) {
            throw ValidationException::withMessages(['channel' => 'Pick a channel.']);
        }

        if ($sourceKey && ($existing = Interaction::query()->where('source_key', $sourceKey)->first())) {
            return $existing;
        }

        return $contact->interactions()->create([
            'user_id' => $by?->id,
            'channel' => $channel,
            'direction' => $direction === 'inbound' ? 'inbound' : 'outbound',
            'subject' => $subject,
            'body' => $body,
            'source_key' => $sourceKey,
            'occurred_at' => $at ?? now(),
        ]);
    }

    /** Trimmed only, so history lookups still match the raw numbers stored on bookings / orders. */
    public static function normalizePhone(?string $phone): ?string
    {
        // ponytail: "0917 123 4567" and "09171234567" stay two identities; normalise at capture time to merge them.
        $phone = trim((string) $phone);

        return $phone !== '' ? $phone : null;
    }
}
