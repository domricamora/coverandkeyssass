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

        $promotions = Promotion::query()->withCount(['coupons', 'coupons as coupons_used_count' => fn ($q) => $q->whereNotNull('used_at')])->latest()->get();

        return \Inertia\Inertia::render('Marketing/Index', [
            'campaigns' => Campaign::query()->latest()->limit(30)->get()->map(fn (Campaign $c) => [
                'id' => $c->id, 'name' => $c->name,
                'when' => $c->created_at->format('M j').($c->scheduled_at ? ' · scheduled '.$c->scheduled_at->format('M j g:i A') : ''),
                'channel' => strtoupper($c->channel), 'status' => $c->status, 'sent' => $c->sent_count,
                'href' => route('marketing.campaigns.show', $c->id),
            ]),
            'automations' => collect($this->automations->all())->map(fn ($a, $type) => [
                'type' => $type, 'label' => $a->label(), 'consent' => $a->needsConsent(),
                'fields' => ['enabled' => (bool) $a->enabled, 'delay_hours' => $a->delay_hours, 'promotion_id' => $a->promotion_id ?? '', 'subject' => $a->subject, 'body' => $a->body],
                'update' => route('marketing.automations.update', $type),
            ])->values(),
            'promotions' => $promotions->map(fn ($p) => [
                'id' => $p->id, 'code' => $p->code, 'label' => $p->label(), 'applies_to' => $p->applies_to, 'active' => (bool) $p->is_active,
                'usage' => 'used '.$p->used_count.' · coupons '.$p->coupons_used_count.'/'.$p->coupons_count.' redeemed',
            ]),
            'audiences' => collect($this->audiences())->map(fn ($label, $key) => [$key, $label])->values(),
            'consented' => Contact::query()->where('marketing_consent', true)->count(),
            'can' => ['manage' => $request->user()->hasPermissionTo('marketing.manage')],
            'urls' => ['store' => route('marketing.campaigns.store'), 'guests' => route('crm.index')],
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

        $count = $this->campaigns->audience($campaign)->count();

        return \Inertia\Inertia::render('Marketing/Campaign', [
            'campaign' => [
                'name' => $campaign->name,
                'status' => $campaign->status,
                'summary' => strtoupper($campaign->channel).' · '.($this->audiences()[$campaign->audience] ?? $campaign->audience).' · '.$count.' opted-in guest(s) reachable'
                    .($campaign->promotion ? ' · personal coupons from '.$campaign->promotion->code : ''),
                'subject' => $campaign->subject,
                'body' => $campaign->body,
                'audience' => $count,
                'recipients' => $campaign->recipients->map(fn ($r) => ['id' => $r->id, 'name' => $r->contact?->name, 'address' => $r->address, 'coupon' => $r->coupon_code, 'at' => $r->sent_at?->format('M j, g:i A')]),
            ],
            'can' => ['send' => $request->user()->hasPermissionTo('marketing.manage') && $campaign->status !== 'sent', 'schedule' => $campaign->status === 'draft'],
            'urls' => ['index' => route('marketing.index'), 'send' => route('marketing.campaigns.send', $campaign->id), 'schedule' => route('marketing.campaigns.schedule', $campaign->id)],
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
