<?php

namespace App\Modules\Loyalty\Services;

use App\Models\User;
use App\Modules\Crm\Models\Contact;
use App\Modules\Loyalty\Models\GiftCard;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gift cards and store credit (Phase 23). Balances only move here, under a
 * row lock, with a redemption line each time — so a card can never be
 * spent twice or below zero. Spent at the POS and on folios.
 */
class GiftCardService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function sell(float $amount, string $paidVia, User $by, ?Contact $contact = null, ?string $expiresOn = null): GiftCard
    {
        if ($amount <= 0 || ! in_array($paidVia, ['cash', 'bank'], true)) {
            $this->fail('amount', 'Enter an amount and how it was paid.');
        }

        $card = GiftCard::create([
            'code' => GiftCard::newCode(), 'kind' => 'gift', 'crm_contact_id' => $contact?->id,
            'initial_value' => $amount, 'balance' => $amount, 'sold_via' => $paidVia, 'expires_on' => $expiresOn, 'issued_by' => $by->id,
        ]);

        $this->audit->log('gift_card.sold', $card, null, ['amount' => $amount, 'via' => $paidVia]);

        return $card;
    }

    /** Store credit for a guest (loyalty reward or goodwill). */
    public function issueCredit(Contact $contact, float $amount, ?User $by = null): GiftCard
    {
        if ($amount <= 0) {
            $this->fail('amount', 'Credit must be above zero.');
        }

        return GiftCard::create([
            'code' => GiftCard::newCode(), 'kind' => 'credit', 'crm_contact_id' => $contact->id,
            'initial_value' => $amount, 'balance' => $amount, 'issued_by' => $by?->id,
        ]);
    }

    /** Spend up to the card's balance against a booking / order reference. Returns the card. */
    public function redeem(string $code, float $amount, string $reference): GiftCard
    {
        return DB::transaction(function () use ($code, $amount, $reference) {
            $card = GiftCard::query()->where('code', strtoupper(trim($code)))->lockForUpdate()->first();

            if (! $card || ! $card->usable()) {
                $this->fail('reference', 'That gift card is not valid or has no balance.');
            }

            if ($amount <= 0 || $amount > (float) $card->balance + 0.004) {
                $this->fail('amount', 'The card has '.\App\Support\Currency::symbol().number_format((float) $card->balance, 2).' left.');
            }

            $card->forceFill(['balance' => round((float) $card->balance - $amount, 2)])->save();
            $card->redemptions()->create(['amount' => $amount, 'reference' => $reference]);

            return $card;
        });
    }

    /** Cancel a card; any unspent balance is recorded (breakage) so the books can release it. */
    public function void(GiftCard $card): void
    {
        DB::transaction(function () use ($card): void {
            $card = GiftCard::query()->lockForUpdate()->findOrFail($card->id);

            if ($card->status === 'void') {
                $this->fail('status', 'Already void.');
            }

            if ((float) $card->balance > 0) {
                $card->redemptions()->create(['amount' => $card->balance, 'reference' => 'VOID']);
            }

            $card->forceFill(['status' => 'void', 'balance' => 0])->save();
        });

        $this->audit->log('gift_card.voided', $card, null, ['code' => $card->code]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
