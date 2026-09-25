<?php

namespace App\Modules\Wallet\Services;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Models\Commission;
use App\Modules\Wallet\Models\CommissionRate;
use App\Modules\Wallet\Models\Payout;
use App\Modules\Wallet\Models\Wallet;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Host wallet & commissions (Phase 08).
 *
 *   payment paid        → commission (platform fee / host amount), host
 *                         amount credited to `pending`
 *   checked out/no-show → pending → `available` (withdrawable)
 *   payment refunded    → host amount taken back from whichever bucket
 *   payout requested    → available − amount; rejected → credited back
 *
 * Every balance change locks the wallet row and writes a ledger row with
 * the balance after it. One commission per payment (unique payment_id).
 */
class WalletService
{
    public const MIN_PAYOUT = 100;

    public function __construct(
        private readonly BookingService $bookings,
        private readonly AuditLogger $audit,
    ) {}

    /** Split a paid payment. Idempotent per payment. */
    public function recordEarning(Payment $payment): ?Commission
    {
        return $this->bookings->asTenantOf($payment, function () use ($payment) {
            // A booking's property or a food order's restaurant (Phase 11).
            [$listing, $label] = $payment->order_id
                ? [($order = $payment->order()->firstOrFail())->restaurant, 'Order '.$order->reference]
                : [($booking = $payment->booking()->with('property')->firstOrFail())->property, 'Booking '.$booking->reference];

            $rate = CommissionRate::resolveFor($listing, ($payment->paid_at ?? now())->toDateString());
            $percent = $rate ? (float) $rate->rate : CommissionRate::defaultRate();

            $gross = (float) $payment->amount;
            $fee = round($gross * $percent / 100, 2);

            return DB::transaction(function () use ($payment, $label, $rate, $percent, $gross, $fee) {
                $wallet = $this->lockedWallet($payment->currency);

                if (Commission::query()->where('payment_id', $payment->id)->exists()) {
                    return null;
                }

                $commission = Commission::create([
                    'booking_id' => $payment->booking_id,
                    'order_id' => $payment->order_id,
                    'payment_id' => $payment->id,
                    'commission_rate_id' => $rate?->id,
                    'gross' => $gross,
                    'rate' => $percent,
                    'platform_fee' => $fee,
                    'host_amount' => $gross - $fee,
                    'status' => Commission::PENDING,
                ]);

                $this->post($wallet, 'earning', 'pending', $gross - $fee, $label.' ('.$percent.'% commission)', commissionId: $commission->id);

                return $commission;
            });
        });
    }

    /** Stay done (checked out / no-show) or food order completed: pending earnings become withdrawable. */
    public function release(Booking|Order $source): void
    {
        $column = $source instanceof Order ? 'order_id' : 'booking_id';
        $label = ($source instanceof Order ? 'order ' : 'booking ').$source->reference;

        DB::transaction(function () use ($source, $column, $label): void {
            $commissions = Commission::query()->where($column, $source->id)->where('status', Commission::PENDING)->get();

            if ($commissions->isEmpty()) {
                return;
            }

            $wallet = $this->lockedWallet();

            foreach ($commissions as $commission) {
                $this->post($wallet, 'release', 'pending', -$commission->host_amount, 'Released: '.$label, commissionId: $commission->id);
                $this->post($wallet, 'release', 'available', (float) $commission->host_amount, 'Released: '.$label, commissionId: $commission->id);
                $commission->forceFill(['status' => Commission::RELEASED, 'released_at' => now()])->save();
            }
        });
    }

    /** Payment refunded: take the host amount back (available may go negative). */
    public function reverse(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $commission = Commission::query()->where('payment_id', $payment->id)->where('status', '!=', Commission::REVERSED)->first();

            if (! $commission) {
                return;
            }

            $wallet = $this->lockedWallet();
            $bucket = $commission->status === Commission::PENDING ? 'pending' : 'available';

            $this->post($wallet, 'reversal', $bucket, -$commission->host_amount, 'Refund: '.strtolower($commission->sourceLabel()), commissionId: $commission->id);
            $commission->forceFill(['status' => Commission::REVERSED, 'reversed_at' => now()])->save();
        });
    }

    /** Host withdrawal request (active tenant). */
    public function requestPayout(float $amount, array $destination, User $user): Payout
    {
        $payout = DB::transaction(function () use ($amount, $destination, $user) {
            $wallet = $this->lockedWallet();

            if ($amount < self::MIN_PAYOUT) {
                $this->fail('The minimum payout is '.$wallet->money(self::MIN_PAYOUT).'.');
            }

            if ($amount > (float) $wallet->available_balance) {
                $this->fail('You can withdraw at most '.$wallet->money($wallet->available_balance).'.');
            }

            $payout = Payout::create($destination + [
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'status' => Payout::REQUESTED,
                'requested_by' => $user->id,
            ]);

            $this->post($wallet, 'payout', 'available', -$amount, 'Payout request #'.$payout->id, payoutId: $payout->id);

            return $payout;
        });

        $this->audit->log('payout.requested', $payout, null, ['amount' => $payout->amount]);

        return $payout;
    }

    /** Super Admin settles a payout: paid (money sent) or rejected (credited back). */
    public function settlePayout(Payout $payout, string $status, User $admin, ?string $reference = null, ?string $note = null): void
    {
        $this->bookings->asTenantOf($payout, function () use ($payout, $status, $admin, $reference, $note): void {
            DB::transaction(function () use ($payout, $status, $admin, $reference, $note): void {
                $wallet = $this->lockedWallet();
                $locked = Payout::query()->lockForUpdate()->findOrFail($payout->id);

                if ($locked->status !== Payout::REQUESTED) {
                    $this->fail('This payout was already processed.');
                }

                $locked->forceFill([
                    'status' => $status,
                    'processed_by' => $admin->id,
                    'processed_at' => now(),
                    'reference' => $reference,
                    'note' => $note,
                ])->save();

                if ($status === Payout::REJECTED) {
                    $this->post($wallet, 'payout_reversal', 'available', (float) $locked->amount, 'Payout #'.$locked->id.' rejected', payoutId: $locked->id);
                }
            });

            $this->audit->log('payout.'.$status, $payout->refresh(), null, ['reference' => $reference, 'note' => $note]);
        });
    }

    /** The active tenant's wallet (created on first use). */
    public function wallet(): Wallet
    {
        return Wallet::query()->firstOrCreate([], ['currency' => 'PHP']);
    }

    private function lockedWallet(string $currency = 'PHP'): Wallet
    {
        Wallet::query()->firstOrCreate([], ['currency' => $currency]);

        return Wallet::query()->lockForUpdate()->firstOrFail();
    }

    private function post(Wallet $wallet, string $type, string $bucket, float $amount, string $description, ?int $commissionId = null, ?int $payoutId = null): void
    {
        $column = $bucket.'_balance';
        $wallet->{$column} = round((float) $wallet->{$column} + $amount, 2);
        $wallet->save();

        $wallet->transactions()->create([
            'tenant_id' => $wallet->tenant_id,
            'type' => $type,
            'bucket' => $bucket,
            'amount' => $amount,
            'balance_after' => $wallet->{$column},
            'commission_id' => $commissionId,
            'payout_id' => $payoutId,
            'description' => $description,
        ]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['amount' => $message]);
    }
}
