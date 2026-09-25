<?php

namespace App\Modules\Loyalty\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Services\CrmService;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\LoyaltyProgram;
use App\Modules\Loyalty\Models\LoyaltyTransaction;
use App\Modules\Loyalty\Models\Reward;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Points (Phase 23). Every change is one post(): the account row is
 * locked, the ledger line carries the balance after it and a source key
 * (unique per business), so earning from a stay or an order can be
 * triggered by events *and* by sync() without ever counting twice.
 *
 *   earn        completed stay / food order: floor(total ÷ ₱ per point)
 *   reversal    that stay / order refunded
 *   referral    both guests, on the new guest's first earning
 *   redeem      reward → personal coupon or store credit
 *   adjust      staff correction
 *
 * Tier follows lifetime points (earn + referral − reversals). Runs inside the tenant.
 */
class LoyaltyService
{
    public function __construct(
        private readonly CrmService $crm,
        private readonly GiftCardService $giftCards,
    ) {}

    public function program(): LoyaltyProgram
    {
        return LoyaltyProgram::query()->firstOrCreate([], ['enabled' => false]);
    }

    public function account(Contact $contact): LoyaltyAccount
    {
        return LoyaltyAccount::query()->firstOrCreate(['crm_contact_id' => $contact->id], ['referral_code' => LoyaltyAccount::newReferralCode($contact->name)]);
    }

    public function earnForBooking(Booking $booking): ?LoyaltyTransaction
    {
        if (! $this->program()->enabled || ! in_array($booking->status, [Booking::CHECKED_OUT, Booking::COMPLETED], true)) {
            return null;
        }

        $contact = $this->crm->upsert($booking->user_id, $booking->guest_name, $booking->guest_email ?? $booking->customer?->email, $booking->guest_phone, 'booking');

        return $contact ? $this->earn($this->account($contact), (float) $booking->total, 'Stay '.$booking->reference, 'booking:'.$booking->id) : null;
    }

    public function earnForOrder(Order $order): ?LoyaltyTransaction
    {
        if (! $this->program()->enabled || $order->status !== Order::COMPLETED) {
            return null;
        }

        $contact = $this->crm->upsert($order->user_id, $order->customer_name, $order->customer?->email, $order->customer_phone, 'order');

        return $contact ? $this->earn($this->account($contact), (float) $order->total, 'Order '.$order->reference, 'order:'.$order->id) : null;
    }

    /** A refunded stay / order gives its points back. */
    public function reverse(string $earnKey, string $description): ?LoyaltyTransaction
    {
        $earn = LoyaltyTransaction::query()->where('source_key', $earnKey)->first();

        return $earn ? $this->post($earn->loyalty_account_id, 'reversal', -$earn->points, $description, $earnKey.':reversal') : null;
    }

    /** Link a new guest to the member who referred them (before their first earning). */
    public function applyReferral(LoyaltyAccount $account, string $code): LoyaltyAccount
    {
        $referrer = LoyaltyAccount::query()->where('referral_code', strtoupper(trim($code)))->first();

        match (true) {
            ! $referrer || $referrer->is($account) => $this->fail('code', 'That referral code is not valid here.'),
            $account->referred_by_id !== null => $this->fail('code', 'A referral is already on this membership.'),
            $account->transactions()->where('type', 'earn')->exists() => $this->fail('code', 'Referral codes apply before your first stay or order.'),
            default => null,
        };

        $account->forceFill(['referred_by_id' => $referrer->id])->save();

        return $account;
    }

    /** @return array{transaction: LoyaltyTransaction, code: ?string} */
    public function redeem(LoyaltyAccount $account, Reward $reward, ?User $by = null): array
    {
        if (! $reward->is_active) {
            $this->fail('reward', 'That reward is not available.');
        }

        return DB::transaction(function () use ($account, $reward, $by) {
            $transaction = $this->post($account->id, 'redeem', -$reward->points_cost, 'Redeemed: '.$reward->name, null, $by);
            $contact = $account->contact;

            $code = $reward->kind === 'coupon'
                ? Coupon::create(['promotion_id' => $reward->promotion_id, 'crm_contact_id' => $contact->id, 'code' => Coupon::newCode($reward->promotion)])->code
                : $this->giftCards->issueCredit($contact, (float) $reward->credit_amount, $by)->code;

            return ['transaction' => $transaction, 'code' => $code];
        });
    }

    public function adjust(LoyaltyAccount $account, int $points, string $reason, User $by): LoyaltyTransaction
    {
        return $this->post($account->id, 'adjust', $points, 'Adjustment: '.$reason, null, $by);
    }

    /** Backfill / catch-up for everything the events might have missed. */
    public function sync(): void
    {
        if (! $this->program()->enabled) {
            return;
        }

        Booking::query()->whereIn('status', [Booking::CHECKED_OUT, Booking::COMPLETED, Booking::REFUNDED])->each(function (Booking $b): void {
            $b->status === Booking::REFUNDED ? $this->reverse('booking:'.$b->id, 'Refunded stay '.$b->reference) : $this->earnForBooking($b);
        });

        Order::query()->whereIn('status', [Order::COMPLETED, Order::REFUNDED])->each(function (Order $o): void {
            $o->status === Order::REFUNDED ? $this->reverse('order:'.$o->id, 'Refunded order '.$o->reference) : $this->earnForOrder($o);
        });
    }

    // ------------------------------------------------------------------

    private function earn(LoyaltyAccount $account, float $amount, string $description, string $key): ?LoyaltyTransaction
    {
        $points = $this->program()->pointsFor($amount);
        $transaction = $this->post($account->id, 'earn', $points, $description.' (₱'.number_format($amount, 2).')', $key);

        if ($account->referred_by_id) {
            $bonus = $this->program()->referral_points;
            $this->post($account->id, 'referral', $bonus, 'Referral welcome bonus', 'referral:'.$account->id.':referee');
            $this->post($account->referred_by_id, 'referral', $bonus, 'Referred '.$account->contact?->name, 'referral:'.$account->id.':referrer');
        }

        return $transaction;
    }

    private function post(int $accountId, string $type, int $points, string $description, ?string $key = null, ?User $by = null): LoyaltyTransaction
    {
        return DB::transaction(function () use ($accountId, $type, $points, $description, $key, $by) {
            if ($key && ($existing = LoyaltyTransaction::query()->where('source_key', $key)->first())) {
                return $existing;
            }

            $account = LoyaltyAccount::query()->lockForUpdate()->findOrFail($accountId);
            $balance = $account->points_balance + $points;

            if ($balance < 0 && in_array($type, ['redeem', 'adjust'], true)) {
                $this->fail('points', 'Only '.$account->points_balance.' points available.');
            }

            $lifetime = in_array($type, ['earn', 'referral', 'reversal'], true) ? max(0, $account->lifetime_points + $points) : $account->lifetime_points;
            $account->forceFill(['points_balance' => $balance, 'lifetime_points' => $lifetime, 'tier' => LoyaltyAccount::tierFor($lifetime)])->save();

            return $account->transactions()->create([
                'type' => $type, 'points' => $points, 'balance_after' => $balance, 'description' => $description, 'source_key' => $key, 'user_id' => $by?->id,
            ]);
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
