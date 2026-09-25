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

        return view('crm::index', [
            'contacts' => $contacts,
            'segments' => collect(Segments::all())->map(fn ($label, $key) => ['label' => $label, 'count' => Segments::apply(Contact::query(), $key)->count()]),
            'segment' => $segment,
            'tags' => Tag::query()->withCount('contacts')->orderBy('name')->get(),
            'title' => 'Guests',
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

        return view('crm::show', [
            'contact' => $contact,
            'bookings' => $contact->bookingsQuery()->with('property')->latest('check_in')->limit(20)->get(),
            'orders' => $contact->ordersQuery()->with('restaurant')->latest()->limit(20)->get(),
            'reservations' => $contact->reservationsQuery()->with('restaurant')->latest('reserved_at')->limit(20)->get(),
            'segments' => collect(Segments::all())->filter(fn ($label, $key) => Segments::apply(Contact::query()->whereKey($contact->id), $key)->exists()),
            'title' => $contact->name,
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
