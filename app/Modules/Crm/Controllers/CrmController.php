<?php

namespace App\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Models\Contact;
use App\Modules\Crm\Models\Interaction;
use App\Modules\Crm\Models\Tag;
use App\Modules\Crm\Services\CrmService;
use App\Modules\Crm\Support\Segments;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Guest contacts, profiles, segments and communication history (Phase 21). */
class CrmController extends Controller
{
    public function __construct(private readonly CrmService $crm) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'crm.view');
        $this->crm->sync();

        $segment = array_key_exists((string) $request->query('segment'), Segments::all()) ? $request->query('segment') : null;
        $q = trim((string) $request->query('q'));

        $contacts = Contact::query()->with('tags')
            ->when($segment, fn ($query) => Segments::apply($query, $segment))
            ->when($request->query('tag'), fn ($query, $tag) => $query->whereHas('tags', fn ($t) => $t->whereKey($tag)))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->orderByDesc('last_activity_at')->orderBy('name')
            ->paginate(30)->withQueryString();

        return \Inertia\Inertia::render('Crm/Index', [
            'contacts' => $contacts->through(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'vip' => (bool) $c->is_vip,
                'reach' => $c->email ?? $c->phone ?? '—',
                'tags' => $c->tags->pluck('name'),
                'stays' => $c->bookings_count,
                'orders' => $c->orders_count + $c->reservations_count,
                'spend' => '₱'.number_format((float) $c->total_spend, 0),
                'seen' => $c->last_activity_at?->diffForHumans() ?? '—',
                'href' => route('crm.show', $c->id),
            ]),
            'segments' => collect(Segments::all())->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => Segments::apply(Contact::query(), $key)->count()])->values(),
            'filters' => ['segment' => $segment, 'q' => $q ?: null, 'tag' => $request->query('tag')],
            'tags' => Tag::query()->withCount('contacts')->orderBy('name')->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'count' => $t->contacts_count]),
            'can' => ['manage' => $request->user()->hasPermissionTo('crm.manage')],
            'urls' => ['self' => route('crm.index'), 'store' => route('crm.store')],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTo($request, 'crm.manage');
        $tenantId = app(TenantContext::class)->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('crm_contacts')->where('tenant_id', $tenantId)],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $contact = Contact::create(['name' => $validated['name'], 'email' => isset($validated['email']) ? mb_strtolower($validated['email']) : null, 'phone' => CrmService::normalizePhone($validated['phone'] ?? null)]);

        return redirect()->route('crm.show', $contact->id)->with('success', 'Guest added.');
    }

    public function show(Request $request, string $contact)
    {
        $this->authorizeTo($request, 'crm.view');
        $contact = $this->crm->refreshMetrics(Contact::query()->with(['tags', 'notes.author', 'interactions.author', 'user'])->findOrFail($contact));

        $c = $contact;
        $history = $c->ordersQuery()->with('restaurant')->latest()->limit(20)->get()->map(fn ($o) => [
            'ref' => $o->reference, 'place' => $o->restaurant?->name, 'when' => $o->created_at->format('M j, Y').' · '.$o->fulfillmentLabel(), 'status' => $o->status, 'total' => $o->money($o->total),
        ])->concat($c->reservationsQuery()->with('restaurant')->latest('reserved_at')->limit(20)->get()->map(fn ($r) => [
            'ref' => $r->reference, 'place' => $r->restaurant?->name, 'when' => $r->reserved_at->format('M j, Y g:i A').' · table for '.$r->party_size, 'status' => $r->status, 'total' => null,
        ]));

        return \Inertia\Inertia::render('Crm/Show', [
            'contact' => [
                'name' => $c->name,
                'vip' => (bool) $c->is_vip,
                'summary' => ($c->email ?? 'no email').' · '.($c->phone ?? 'no phone').' · '.($c->user ? 'has an account' : 'no account').' · first seen '.($c->first_seen_at?->format('M j, Y') ?? '—'),
                'segments' => collect(Segments::all())->filter(fn ($label, $key) => Segments::apply(Contact::query()->whereKey($c->id), $key)->exists())->values(),
                'tags' => $c->tags->pluck('name'),
                'stats' => [['Lifetime spend', '₱'.number_format((float) $c->total_spend, 2)], ['Stays', $c->bookings_count], ['Food orders', $c->orders_count], ['Table visits', $c->reservations_count]],
                'fields' => ['name' => $c->name, 'email' => $c->email ?? '', 'phone' => $c->phone ?? '', 'tags' => $c->tags->pluck('name')->implode(', '), 'is_vip' => (bool) $c->is_vip, 'marketing_consent' => (bool) $c->marketing_consent],
                'consent_at' => $c->consent_at?->format('M j, Y'),
                'notes' => $c->notes->map(fn ($n) => ['id' => $n->id, 'body' => $n->body, 'by' => ($n->author?->name ?? '—').' · '.$n->created_at->format('M j, Y')]),
                'interactions' => $c->interactions->map(fn ($i) => [
                    'id' => $i->id, 'head' => Str::headline($i->channel).' · '.$i->direction.' · '.$i->occurred_at->format('M j, g:i A').' · '.($i->author?->name ?? 'system'),
                    'subject' => $i->subject, 'body' => $i->body ? Str::limit($i->body, 300) : null,
                ]),
            ],
            'bookings' => $c->bookingsQuery()->with('property')->latest('check_in')->limit(20)->get()->map(fn ($b) => [
                'ref' => $b->reference, 'place' => $b->property?->name, 'when' => $b->check_in->format('M j').'–'.$b->check_out->format('M j, Y'), 'status' => $b->status, 'total' => '₱'.number_format((float) $b->total, 2),
            ]),
            'history' => $history,
            'channels' => Interaction::CHANNELS,
            'can' => ['manage' => $request->user()->hasPermissionTo('crm.manage')],
            'urls' => ['index' => route('crm.index'), 'update' => route('crm.update', $c->id), 'note' => route('crm.notes.store', $c->id), 'interaction' => route('crm.interactions.store', $c->id)],
        ]);
    }

    public function update(Request $request, string $contact)
    {
        $this->authorizeTo($request, 'crm.manage');
        $contact = Contact::query()->findOrFail($contact);
        $tenantId = app(TenantContext::class)->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('crm_contacts')->where('tenant_id', $tenantId)->ignore($contact->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $contact->forceFill([
            'name' => $validated['name'],
            'email' => isset($validated['email']) ? mb_strtolower($validated['email']) : null,
            'phone' => CrmService::normalizePhone($validated['phone'] ?? null),
            'is_vip' => $request->boolean('is_vip'),
        ])->save();

        if ($request->boolean('marketing_consent') !== $contact->marketing_consent) {
            $this->crm->setConsent($contact, $request->boolean('marketing_consent'));
        }

        $this->crm->setTags($contact, explode(',', (string) ($validated['tags'] ?? '')));

        return back()->with('success', 'Guest profile saved.');
    }

    public function note(Request $request, string $contact)
    {
        $this->authorizeTo($request, 'crm.manage');
        $this->crm->note(Contact::query()->findOrFail($contact), $request->validate(['body' => ['required', 'string', 'max:5000']])['body'], $request->user());

        return back()->with('success', 'Note added.');
    }

    public function interaction(Request $request, string $contact)
    {
        $this->authorizeTo($request, 'crm.manage');

        $validated = $request->validate([
            'channel' => ['required', Rule::in(Interaction::CHANNELS)],
            'direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->crm->log(Contact::query()->findOrFail($contact), $validated['channel'], $validated['direction'], $validated['subject'] ?? null, $validated['body'] ?? null, $request->user());

        return back()->with('success', 'Conversation logged.');
    }

    private function authorizeTo(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermissionTo($permission), 403);
    }
}
