<?php

namespace App\Modules\PropertyManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Models\Amenity;
use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Media;
use App\Modules\Marketplace\Models\Property;
use App\Modules\Marketplace\Models\PropertyType;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\MediaUploads;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Host-side property management (Phase 04): profile CRUD, publish
 * lifecycle, amenities/policies/location editing and media (photos +
 * videos). Property-level authorization flows through the Marketplace
 * PropertyPolicy; {property}/{media} params are resolved manually — see
 * PropertyManagementController.
 */
class PropertyController extends PropertyManagementController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Property::class);

        $properties = Property::query()
            ->with(['location', 'propertyType'])
            ->withCount(['roomTypes', 'rooms', 'staff'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Properties/Index', [
            'properties' => $properties->through(fn (Property $p) => [
                'name' => $p->name,
                'where' => $p->locationLabel() ?: null,
                'type' => $p->propertyType?->name,
                'status' => $p->status,
                'from' => $p->priceLabel(),
                'rooms' => $p->rooms_count,
                'urls' => ['show' => route('properties.show', $p), 'inventory' => route('properties.inventory', $p), 'staff' => route('properties.staff.index', $p)],
            ]),
            'can' => ['create' => $request->user()->can('create', Property::class)],
            'urls' => ['create' => route('properties.create')],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Property::class);

        return Inertia::render('Properties/Form', [
            'property' => null,
            'options' => $this->formOptions(),
            'urls' => ['submit' => route('properties.store'), 'back' => route('properties.index')],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Property::class);

        \App\Modules\Billing\Support\Usage::ensureRoom(app(\App\Support\TenantContext::class)->tenant(), 'properties');
        $validated = $request->validate($this->profileRules());

        $property = Property::create($validated + [
            'host_id' => $request->user()->id,
            'status' => Property::STATUS_DRAFT,
        ]);

        $this->audit->log('property.created', $property, null, ['name' => $property->name]);

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Property created. It stays a draft until you publish it.');
    }

    public function show(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('view', $property);

        $property->load(['location', 'propertyType', 'media']);
        $property->loadCount(['roomTypes', 'rooms', 'staff']);

        $user = $request->user();
        $cover = $property->coverMedia();

        return Inertia::render('Properties/Show', [
            'property' => [
                'name' => $property->name,
                'summary' => ($property->propertyType?->name ?? 'Property').' · '.($property->locationLabel() ?: 'No destination set'),
                'featured' => (bool) $property->is_featured,
                'status' => $property->status,
                'published' => $property->status === Property::STATUS_PUBLISHED,
                'tagline' => $property->tagline,
                'description' => $property->description,
                'facts' => [
                    ['From', $property->priceLabel().' / night · cleaning '.$property->currency.' '.number_format((float) $property->cleaning_fee, 0)],
                    ['Capacity', 'up to '.$property->max_guests.' guests · '.$property->bedrooms.' bedroom(s) · '.$property->beds.' bed(s) · '.$property->bathrooms.' bath(s)'],
                    ['Check-in / out', $property->check_in_time.' / '.$property->check_out_time],
                    $property->amenities->isNotEmpty() ? ['Amenities', $property->amenities->pluck('name')->implode(', ')] : null,
                ],
                'policies' => collect($property->policies ?? [])->map(fn ($v, $k) => [Str::headline($k), (string) $v])->values(),
                'counts' => ['types' => $property->room_types_count, 'rooms' => $property->rooms_count, 'staff' => $property->staff_count],
                'cover' => $cover?->url(),
                'photos' => $property->media->where('kind', 'image')->count(),
                'videos' => $property->media->where('kind', 'video')->count(),
                'public' => route('marketplace.properties.show', $property->slug),
            ],
            'tabs' => $this->tabs($property, 'show'),
            'can' => ['publish' => $user->can('publish', $property), 'delete' => $user->can('delete', $property)],
            'urls' => [
                'publish' => route('properties.publish', $property),
                'unpublish' => route('properties.unpublish', $property),
                'destroy' => route('properties.destroy', $property),
            ],
        ]);
    }

    public function edit(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('update', $property);

        $property->load('media');

        $policies = $property->policies ?? [];

        return Inertia::render('Properties/Form', [
            'property' => $property->only(array_keys($this->profileRules())) + [
                'amenities' => $property->amenities()->pluck('amenities.id'),
                'policy_free_cancellation_days' => $policies['free_cancellation_days'] ?? '',
            ] + collect(['cancellation', 'children', 'pets', 'noise', 'smoking'])->mapWithKeys(fn ($k) => ['policy_'.$k => $policies[$k] ?? ''])->all(),
            'title' => $property->name,
            'options' => $this->formOptions() + ['amenities' => Amenity::query()->orderBy('name')->get(['id', 'name'])->map(fn ($a) => [$a->id, $a->name])],
            'media' => MediaUploads::payload($property, fn ($m) => [
                'make_cover' => route('properties.media.cover', [$property, $m]),
                'destroy' => route('properties.media.destroy', [$property, $m]),
            ]) + ['store' => route('properties.media.store', $property), 'areas' => MediaUploads::AREAS['property']],
            'tabs' => $this->tabs($property, 'edit'),
            'urls' => ['submit' => route('properties.update', $property), 'back' => route('properties.show', $property)],
        ]);
    }

    public function update(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('update', $property);

        $rules = $this->profileRules();
        $old = $property->only(array_keys($rules));

        $validated = $request->validate($rules);

        $property->update($validated);

        $amenities = $request->validate([
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
        ]);

        $property->amenities()->sync($amenities['amenities'] ?? []);

        $property->update(['policies' => $this->policiesFrom($request, $property->policies ?? [])]);

        $this->audit->log(
            'property.updated',
            $property,
            $old,
            $property->only(array_keys($rules)),
        );

        return redirect()->route('properties.show', $property)->with('success', 'Property profile updated.');
    }

    public function publish(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('publish', $property);

        $was = $property->status;
        $property->publish();

        $this->audit->log('property.published', $property, ['status' => $was], ['status' => $property->status]);

        return back()->with('success', 'Property is live on the marketplace.');
    }

    public function unpublish(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('publish', $property);

        $was = $property->status;
        $property->unpublish();

        $this->audit->log('property.unpublished', $property, ['status' => $was], ['status' => $property->status]);

        return back()->with('success', 'Property unpublished — hidden from the marketplace.');
    }

    public function destroy(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('delete', $property);

        $old = ['name' => $property->name, 'slug' => $property->slug];

        $property->delete();

        $this->audit->log('property.deleted', $property, $old, null);

        return redirect()->route('properties.index')->with('success', 'Property deleted.');
    }

    // ------------------------------------------------------------------
    // Media (photos + videos)
    // ------------------------------------------------------------------

    public function addMedia(Request $request, string $property)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('update', $property);

        $added = \App\Support\MediaUploads::store($request, $property, 'media/properties');

        return back()->with('success', $request->input('kind') === 'video' ? 'Video added.' : ($added === 1 ? 'Photo added.' : $added.' photos added.'));
    }

    public function setCover(Request $request, string $property, string $media)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('update', $property);

        $owned = $property->media()->whereKey($media)->firstOrFail();

        DB::transaction(function () use ($property, $owned): void {
            $property->media()->update(['is_cover' => false]);
            $owned->forceFill(['is_cover' => true])->save();
        });

        return back()->with('success', 'Cover updated.');
    }

    public function destroyMedia(Request $request, string $property, string $media)
    {
        $property = $this->resolveProperty($property);

        $this->authorize('update', $property);

        $owned = $property->media()->whereKey($media)->firstOrFail();

        $owned->delete();

        return back()->with('success', 'Media removed.');
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function formOptions(): array
    {
        return [
            'types' => PropertyType::query()->orderBy('name')->get(['id', 'name'])->map(fn ($t) => [$t->id, $t->name]),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name'])->map(fn ($l) => [$l->id, $l->name]),
        ];
    }

    private function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'property_type_id' => ['nullable', 'exists:property_types,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:99'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:999'],
            'beds' => ['nullable', 'integer', 'min:0', 'max:999'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:999'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'weekend_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Merge the free-form policy textareas into the property's policies
     * JSON, dropping emptied fields instead of storing blanks.
     *
     * @param  array<string, string>  $current
     * @return array<string, string>
     */
    private function policiesFrom(Request $request, array $current): array
    {
        // Structured free-cancellation window (drives the marketplace badge and filter); empty = non-refundable.
        if ($request->has('policy_free_cancellation_days')) {
            $days = $request->validate(['policy_free_cancellation_days' => ['nullable', 'integer', 'min:0', 'max:60']])['policy_free_cancellation_days'] ?? null;

            if ($days === null || $days === '') {
                unset($current['free_cancellation_days']);
            } else {
                $current['free_cancellation_days'] = (int) $days;
            }
        }

        foreach (['cancellation', 'children', 'pets', 'noise', 'smoking'] as $field) {
            $value = trim((string) $request->input("policy_{$field}", ''));

            if ($value !== '') {
                $current[$field] = $value;
            } else {
                unset($current[$field]);
            }
        }

        return $current;
    }
}
