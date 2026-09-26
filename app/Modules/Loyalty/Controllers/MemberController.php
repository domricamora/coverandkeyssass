<?php

namespace App\Modules\Loyalty\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Crm\Models\Contact;
use App\Modules\Loyalty\Models\GiftCard;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Support\TenantContext;
use Illuminate\Http\Request;

/**
 * Guest side (Phase 23): the signed-in customer's memberships at every
 * business, credit / gift card balances, and entering a referral code.
 * Tenant scopes are lifted only through the customer's own contacts.
 */
class MemberController extends Controller
{
    public function index(Request $request)
    {
        $contactIds = Contact::query()->withoutGlobalScope('tenant')->where('user_id', $request->user()->id)->pluck('id');

        return \Inertia\Inertia::render('Account/Rewards', [
            'accounts' => LoyaltyAccount::query()->withoutGlobalScope('tenant')->whereIn('crm_contact_id', $contactIds)
                ->with(['transactions' => fn ($q) => $q->withoutGlobalScope('tenant')->limit(5)])->get()
                ->map(function ($a) {
                    $next = $a->nextTier();

                    return [
                        'id' => $a->id,
                        'business' => Tenant::query()->find($a->tenant_id)?->name,
                        'tier' => $a->tierLabel(),
                        'points' => (int) $a->points_balance,
                        'next' => $next ? number_format($next[1]).' more to '.$next[0] : null,
                        'code' => $a->referral_code,
                        'canRefer' => ! $a->referred_by_id,
                        'referral' => route('account.loyalty.referral', $a->id),
                        'activity' => $a->transactions->map(fn ($t) => ['id' => $t->id, 'text' => $t->description, 'points' => (int) $t->points]),
                    ];
                }),
            'cards' => GiftCard::query()->withoutGlobalScope('tenant')->whereIn('crm_contact_id', $contactIds)->where('status', 'active')->where('balance', '>', 0)->get()
                ->map(fn ($c) => ['id' => $c->id, 'business' => Tenant::query()->find($c->tenant_id)?->name, 'code' => $c->code, 'balance' => '₱'.number_format((float) $c->balance, 2)]),
            'tabs' => \App\Modules\Customer\Controllers\AccountController::nav('account.loyalty'),
        ]);
    }

    public function referral(Request $request, string $account)
    {
        $contactIds = Contact::query()->withoutGlobalScope('tenant')->where('user_id', $request->user()->id)->pluck('id');
        $account = LoyaltyAccount::query()->withoutGlobalScope('tenant')->whereIn('crm_contact_id', $contactIds)->findOrFail($account);

        app(TenantContext::class)->runAs($account, fn () => app(LoyaltyService::class)->applyReferral($account, (string) $request->validate(['code' => ['required', 'string', 'max:20']])['code']));

        return back()->with('success', 'Referral code applied — you both earn a bonus after your first stay or order.');
    }
}
