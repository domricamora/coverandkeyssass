<?php

namespace App\Modules\Loyalty\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Promotion;
use App\Modules\Crm\Models\Contact;
use App\Modules\Loyalty\Models\GiftCard;
use App\Modules\Loyalty\Models\LoyaltyAccount;
use App\Modules\Loyalty\Models\Reward;
use App\Modules\Loyalty\Services\GiftCardService;
use App\Modules\Loyalty\Services\LoyaltyService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Host loyalty screens (Phase 23): programme, members, rewards, gift cards. */
class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyalty,
        private readonly GiftCardService $giftCards,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'loyalty.view');
        $this->loyalty->sync();
        $q = trim((string) $request->query('q'));

        return view('loyalty::index', [
            'program' => $this->loyalty->program(),
            'members' => LoyaltyAccount::query()->with('contact')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->whereHas('contact', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))->orWhere('referral_code', strtoupper($q))))
                ->orderByDesc('lifetime_points')->paginate(25)->withQueryString(),
            'tierCounts' => LoyaltyAccount::query()->selectRaw('tier, COUNT(*) AS n')->groupBy('tier')->pluck('n', 'tier'),
            'rewards' => Reward::query()->with('promotion')->orderBy('points_cost')->get(),
            'promotions' => Promotion::query()->where('is_active', true)->orderBy('code')->get(),
            'giftCards' => GiftCard::query()->with('contact')->latest()->limit(20)->get(),
            'outstanding' => round((float) GiftCard::query()->where('status', 'active')->sum('balance'), 2),
            'contacts' => Contact::query()->orderBy('name')->limit(500)->get(['id', 'name', 'email']),
            'title' => 'Loyalty',
        ]);
    }

    public function updateProgram(Request $request)
    {
        $this->authorizeTo($request, 'loyalty.manage');
        $this->loyalty->program()->update($request->validate([
            'pesos_per_point' => ['required', 'numeric', 'min:1', 'max:100000'],
            'referral_points' => ['required', 'integer', 'min:0', 'max:100000'],
        ]) + ['enabled' => $request->boolean('enabled')]);

        return back()->with('success', 'Programme saved.');
    }

    public function storeReward(Request $request)
    {
        $this->authorizeTo($request, 'loyalty.manage');
        $tenantId = app(TenantContext::class)->id();

        Reward::create($request->validate([
            'name' => ['required', 'string', 'max:160'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'kind' => ['required', Rule::in(['coupon', 'credit'])],
            'promotion_id' => ['nullable', 'required_if:kind,coupon', 'integer', Rule::exists('promotions', 'id')->where('tenant_id', $tenantId)],
            'credit_amount' => ['nullable', 'required_if:kind,credit', 'numeric', 'min:1'],
        ]));

        return back()->with('success', 'Reward added.');
    }

    public function toggleReward(Request $request, string $reward)
    {
        $this->authorizeTo($request, 'loyalty.manage');
        $reward = Reward::query()->findOrFail($reward);
        $reward->update(['is_active' => ! $reward->is_active]);

        return back()->with('success', $reward->name.' '.($reward->is_active ? 'available' : 'retired').'.');
    }

    public function member(Request $request, string $account)
    {
        $this->authorizeTo($request, 'loyalty.view');
        $account = LoyaltyAccount::query()->with(['contact', 'referrer.contact'])->findOrFail($account);

        return view('loyalty::member', [
            'account' => $account,
            'transactions' => $account->transactions()->with('user')->limit(50)->get(),
            'rewards' => Reward::query()->where('is_active', true)->orderBy('points_cost')->get(),
            'cards' => GiftCard::query()->where('crm_contact_id', $account->crm_contact_id)->latest()->get(),
            'title' => $account->contact?->name ?? 'Member',
        ]);
    }

    public function enroll(Request $request, string $contact)
    {
        $this->authorizeTo($request, 'loyalty.manage');
        $account = $this->loyalty->account(Contact::query()->findOrFail($contact));

        return redirect()->route('loyalty.members.show', $account->id)->with('success', 'Enrolled.');
    }

    public function redeem(Request $request, string $account)
    {
        $this->authorizeTo($request, 'loyalty.manage');
        $result = $this->loyalty->redeem(LoyaltyAccount::query()->findOrFail($account), Reward::query()->findOrFail($request->validate(['reward_id' => ['required', 'integer']])['reward_id']), $request->user());

        return back()->with('success', 'Redeemed — code '.$result['code'].'.');
    }

    public function adjust(Request $request, string $account)
    {
        $this->authorizeTo($request, 'loyalty.manage');
        $validated = $request->validate(['points' => ['required', 'integer', 'not_in:0'], 'reason' => ['required', 'string', 'max:160']]);
        $this->loyalty->adjust(LoyaltyAccount::query()->findOrFail($account), (int) $validated['points'], $validated['reason'], $request->user());

        return back()->with('success', 'Points adjusted.');
    }

    public function sellGiftCard(Request $request)
    {
        $this->authorizeTo($request, 'loyalty.manage');
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'paid_via' => ['required', Rule::in(['cash', 'bank'])],
            'crm_contact_id' => ['nullable', 'integer'],
            'expires_on' => ['nullable', 'date', 'after:today'],
        ]);

        $card = $this->giftCards->sell((float) $validated['amount'], $validated['paid_via'], $request->user(),
            isset($validated['crm_contact_id']) ? Contact::query()->findOrFail($validated['crm_contact_id']) : null, $validated['expires_on'] ?? null);

        return back()->with('success', 'Gift card '.$card->code.' issued for ₱'.number_format((float) $card->initial_value, 2).'.');
    }

    public function voidGiftCard(Request $request, string $card)
    {
        $this->authorizeTo($request, 'loyalty.manage');
        $this->giftCards->void(GiftCard::query()->findOrFail($card));

        return back()->with('success', 'Gift card voided.');
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
