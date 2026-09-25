<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Booking\Models\Booking;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Services\CrmService;
use App\Modules\Crm\Support\Segments;
use App\Modules\Marketing\Models\Automation;
use App\Modules\Marketing\Models\SavedCart;
use App\Modules\Ordering\Models\Order;

/**
 * Automated follow-ups (Phase 22), run by `marketing:run`. Each trigger
 * has a stable source key, so a guest gets each message once.
 *
 *   abandoned_booking  marketplace booking still pending / held after the delay (service)
 *   abandoned_cart     saved cart untouched for the delay, no order since (marketing)
 *   review_request     checked out, delay passed (service)
 *   post_stay          checked out, delay passed, optional coupon (marketing)
 *   reactivation       CRM "Inactive" segment, optional coupon, once per inactive spell (marketing)
 *
 * Marketing follow-ups require the contact's consent; service ones do not.
 */
class AutomationService
{
    public function __construct(
        private readonly MessageSender $sender,
        private readonly CrmService $crm,
    ) {}

    /** The business's automations, created (disabled) with defaults on first use. */
    public function all()
    {
        foreach (Automation::DEFAULTS as $type => [, $delay, $subject, $body]) {
            Automation::query()->firstOrCreate(['type' => $type], ['delay_hours' => $delay, 'subject' => $subject, 'body' => $body, 'enabled' => false]);
        }

        return Automation::query()->with('promotion')->get()->keyBy('type');
    }

    /** @return array<string, int> messages sent per automation */
    public function run(): array
    {
        $sent = [];

        foreach ($this->all()->where('enabled', true) as $type => $automation) {
            $sent[$type] = match ($type) {
                'abandoned_booking' => $this->abandonedBookings($automation),
                'abandoned_cart' => $this->abandonedCarts($automation),
                'review_request' => $this->checkedOut($automation, fn (Booking $b) => route('account.bookings.show', $b->reference)),
                'post_stay' => $this->checkedOut($automation, fn (Booking $b) => route('marketplace.properties.show', $b->property->slug)),
                'reactivation' => $this->reactivation($automation),
                default => 0,
            };
        }

        return $sent;
    }

    private function abandonedBookings(Automation $a): int
    {
        return Booking::query()->whereIn('status', [Booking::PENDING, Booking::HELD])->where('source', Booking::SOURCE_MARKETPLACE)
            ->whereNotNull('user_id')->where('created_at', '<=', now()->subHours($a->delay_hours))->with('customer')->get()
            ->sum(fn (Booking $b) => $this->deliver($a, $this->contactFor($b), 'auto:abandoned_booking:'.$b->id, route('account.bookings.show', $b->reference)));
    }

    private function abandonedCarts(Automation $a): int
    {
        return SavedCart::query()->where('updated_at', '<=', now()->subHours($a->delay_hours))->with(['user', 'restaurant'])->get()
            ->filter(fn (SavedCart $cart) => $cart->lines !== [] && ! Order::query()->where('user_id', $cart->user_id)->where('restaurant_id', $cart->restaurant_id)->where('created_at', '>=', $cart->updated_at)->exists())
            ->sum(fn (SavedCart $cart) => $this->deliver($a,
                $this->crm->upsert($cart->user_id, $cart->user->name, $cart->user->email, null, 'order'),
                'auto:abandoned_cart:'.$cart->id.':'.$cart->updated_at->timestamp,
                route('marketplace.restaurants.show', $cart->restaurant->slug)));
    }

    private function checkedOut(Automation $a, \Closure $link): int
    {
        return Booking::query()->whereIn('status', [Booking::CHECKED_OUT, Booking::COMPLETED])->whereNotNull('user_id')
            ->where('checked_out_at', '<=', now()->subHours($a->delay_hours))->with(['customer', 'property'])->get()
            ->sum(fn (Booking $b) => $this->deliver($a, $this->contactFor($b), 'auto:'.$a->type.':'.$b->id, $link($b)));
    }

    private function reactivation(Automation $a): int
    {
        return Segments::apply(Contact::query(), 'inactive')->get()
            ->sum(fn (Contact $c) => $this->deliver($a, $c, 'auto:reactivation:'.$c->id.':'.$c->last_activity_at?->toDateString(), ''));
    }

    private function contactFor(Booking $b): ?Contact
    {
        return $this->crm->upsert($b->user_id, $b->guest_name, $b->guest_email ?? $b->customer?->email, $b->guest_phone, 'booking');
    }

    private function deliver(Automation $a, ?Contact $contact, string $key, string $link): int
    {
        if (! $contact || ($a->needsConsent() && ! $contact->marketing_consent)) {
            return 0;
        }

        $before = $this->sender->alreadySent($key);
        $this->sender->send($contact, 'email', $a->subject, $a->body, $key, $a->needsConsent() ? $a->promotion : null, ['link' => $link], $a->needsConsent());

        return (! $before && $this->sender->alreadySent($key)) ? 1 : 0;
    }
}
