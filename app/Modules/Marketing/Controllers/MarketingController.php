<?php

namespace App\Modules\Marketing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Promotion;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Models\Tag;
use App\Modules\Crm\Support\Segments;
use App\Modules\Marketing\Models\Automation;
use App\Modules\Marketing\Models\Campaign;
use App\Modules\Marketing\Models\Coupon;
use App\Modules\Marketing\Services\AutomationService;
use App\Modules\Marketing\Services\CampaignService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Campaigns, promotions / coupons overview and automations (Phase 22). */
class MarketingController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly AutomationService $automations,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'marketing.view');

        return view('marketing::index', [
            'campaigns' => Campaign::query()->latest()->limit(30)->get(),
            'automations' => $this->automations->all(),
            'promotions' => Promotion::query()->withCount(['coupons', 'coupons as coupons_used_count' => fn ($q) => $q->whereNotNull('used_at')])->latest()->get(),
            'audiences' => $this->audiences(),
            'consented' => Contact::query()->where('marketing_consent', true)->count(),
            'title' => 'Marketing',
        ]);
    }

    public function storeCampaign(Request $request)
    {
        $this->authorizeTo($request, 'marketing.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'audience' => ['required', Rule::in(array_keys($this->audiences()))],
            'subject' => ['nullable', 'required_if:channel,email', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'promotion_id' => ['nullable', 'integer', Rule::exists('promotions', 'id')->where('tenant_id', app(TenantContext::class)->id())],
        ]);

        $campaign = Campaign::create($validated + ['created_by' => $request->user()->id]);

        return redirect()->route('marketing.campaigns.show', $campaign->id)->with('success', 'Campaign drafted — check the audience, then send.');
    }

    public function showCampaign(Request $request, string $campaign)
    {
        $this->authorizeTo($request, 'marketing.view');
        $campaign = Campaign::query()->with(['promotion', 'recipients.contact'])->findOrFail($campaign);

        return view('marketing::campaign', [
            'campaign' => $campaign,
            'audienceCount' => $this->campaigns->audience($campaign)->count(),
            'audienceLabel' => $this->audiences()[$campaign->audience] ?? $campaign->audience,
            'title' => $campaign->name,
        ]);
    }

    public function sendCampaign(Request $request, string $campaign)
    {
        $this->authorizeTo($request, 'marketing.manage');
        $campaign = $this->campaigns->send(Campaign::query()->findOrFail($campaign));

        return back()->with('success', 'Sent to '.$campaign->sent_count.' guest(s).');
    }

    public function scheduleCampaign(Request $request, string $campaign)
    {
        $this->authorizeTo($request, 'marketing.manage');
        $at = $request->validate(['scheduled_at' => ['required', 'date', 'after:now']])['scheduled_at'];
        $this->campaigns->schedule(Campaign::query()->findOrFail($campaign), $at);

        return back()->with('success', 'Campaign scheduled.');
    }

    public function updateAutomation(Request $request, string $type)
    {
        $this->authorizeTo($request, 'marketing.manage');
        abort_unless(array_key_exists($type, Automation::DEFAULTS), 404);
        $this->automations->all();

        $validated = $request->validate([
            'delay_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'promotion_id' => ['nullable', 'integer', Rule::exists('promotions', 'id')->where('tenant_id', app(TenantContext::class)->id())],
        ]);

        Automation::query()->where('type', $type)->firstOrFail()->update($validated + ['enabled' => $request->boolean('enabled')]);

        return back()->with('success', Automation::DEFAULTS[$type][0].' saved.');
    }

    /** @return array<string, string> */
    private function audiences(): array
    {
        return ['all' => 'Everyone who opted in']
            + collect(Segments::all())->mapWithKeys(fn ($label, $key) => ['segment:'.$key => 'Segment: '.$label])->all()
            + Tag::query()->orderBy('name')->get()->mapWithKeys(fn ($t) => ['tag:'.$t->id => 'Tag: '.$t->name])->all();
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
