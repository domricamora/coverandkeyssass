<?php

namespace App\Modules\RestaurantManagement\Controllers;

use App\Modules\Marketplace\Models\Cuisine;
use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Restaurant;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use App\Support\MediaUploads;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Host-side restaurant profile (Phase 09): CRUD, opening hours, cuisines,
 * publish lifecycle and photos. Menu and floor plan live in their own
 * controllers.
 */
class RestaurantController extends RestaurantManagementController
{
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request)
    {
        $this->authorizeTo($request, 'restaurants.view');

        return Inertia::render('Restaurants/Index', [
            'restaurants' => Restaurant::query()->with('location')->withCount(['menuItems', 'tables'])->latest()->paginate(15)
                ->through(fn (Restaurant $r) => [
                    'name' => $r->name,
                    'where' => $r->locationLabel() ?: null,
                    'status' => $r->status,
                    'items' => $r->menu_items_count,
                    'tables' => $r->tables_count,
                    'urls' => ['show' => route('restaurants.show', $r), 'menu' => route('restaurants.menu', $r), 'orders' => route('restaurants.orders.index', $r), 'reservations' => route('restaurants.reservations', $r)],
                ]),
            'can' => ['create' => $request->user()->hasPermissionTo('restaurants.create')],
            'urls' => ['create' => route('restaurants.create')],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeTo($request, 'restaurants.create');

        return Inertia::render('Restaurants/Form', $this->formData(null) + [
            'urls' => ['submit' => route('restaurants.store'), 'back' => route('restaurants.index')],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTo($request, 'restaurants.create');

        \App\Modules\Billing\Support\Usage::ensureRoom(app(\App\Support\TenantContext::class)->tenant(), 'restaurants');
        $validated = $request->validate($this->rules());

        $restaurant = Restaurant::create($this->attributes($request, $validated) + [
            'host_id' => $request->user()->id,
            'status' => Restaurant::STATUS_DRAFT,
        ]);
        $restaurant->cuisines()->sync($validated['cuisines'] ?? []);

        $this->audit->log('restaurant.created', $restaurant, null, ['name' => $restaurant->name]);

        return redirect()->route('restaurants.show', $restaurant)
            ->with('success', 'Restaurant created. It stays a draft until you publish it.');
    }

    public function show(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'restaurants.view');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->load(['location', 'cuisines', 'media']);
        $restaurant->loadCount(['menuCategories', 'menuItems', 'diningAreas', 'tables']);

        $r = $restaurant;
        $onOff = fn ($v) => $v ? 'On' : 'Off';
        $user = $request->user();

        return Inertia::render('Restaurants/Show', [
            'restaurant' => [
                'name' => $r->name,
                'summary' => ($r->cuisines->pluck('name')->implode(', ') ?: 'Restaurant').' · '.($r->locationLabel() ?: 'No destination set').' · '.$r->priceLevelLabel(),
                'status' => $r->status,
                'published' => $r->isPublished(),
                'tagline' => $r->tagline,
                'description' => $r->description,
                'facts' => [
                    ['Address', $r->address_line ?: '—'],
                    ['Contact', ($r->phone ?: '—').' · '.($r->email ?: '—')],
                    ['Reservations', $onOff($r->reservations_enabled)],
                    ['Online ordering', $onOff($r->ordering_enabled)],
                    ['Delivery', $onOff($r->delivery_enabled)],
                    ['Tax', (float) $r->tax_rate.'% '.($r->tax_inclusive ? 'included in prices' : 'added at checkout')],
                ],
                'hours' => collect($r->opening_hours ?? [])->map(fn ($v, $k) => [ucfirst($k), $v])->values(),
                'counts' => ['categories' => $r->menu_categories_count, 'items' => $r->menu_items_count, 'tables' => $r->tables_count, 'areas' => $r->dining_areas_count],
                'cover' => $r->coverMedia()?->url(),
                'public' => route('marketplace.restaurants.show', $r->slug),
            ],
            'tabs' => $this->tabs($r, 'show'),
            'can' => ['publish' => $user->hasPermissionTo('restaurants.publish'), 'delete' => $user->hasPermissionTo('restaurants.delete')],
            'urls' => ['publish' => route('restaurants.publish', $r), 'unpublish' => route('restaurants.unpublish', $r), 'destroy' => route('restaurants.destroy', $r), 'floor' => route('floor.index', ['restaurant' => $r->slug])],
        ]);
    }

    public function edit(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'restaurants.update');
        $restaurant = $this->resolveRestaurant($restaurant);

        return Inertia::render('Restaurants/Form', $this->formData($restaurant) + [
            'media' => MediaUploads::payload($restaurant, fn ($m) => [
                'make_cover' => route('restaurants.media.cover', [$restaurant, $m]),
                'destroy' => route('restaurants.media.destroy', [$restaurant, $m]),
            ]) + ['store' => route('restaurants.media.store', $restaurant), 'areas' => MediaUploads::AREAS['restaurant']],
            'tabs' => $this->tabs($restaurant, 'edit'),
            'urls' => ['submit' => route('restaurants.update', $restaurant), 'back' => route('restaurants.show', $restaurant)],
        ]);
    }

    public function update(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'restaurants.update');
        $restaurant = $this->resolveRestaurant($restaurant);

        $validated = $request->validate($this->rules());
        $attributes = $this->attributes($request, $validated);
        $old = $restaurant->only(array_keys($attributes));

        $restaurant->update($attributes);
        $restaurant->cuisines()->sync($validated['cuisines'] ?? []);

        $this->audit->log('restaurant.updated', $restaurant, $old, $restaurant->only(array_keys($attributes)));

        return redirect()->route('restaurants.show', $restaurant)->with('success', 'Restaurant profile updated.');
    }

    public function publish(Request $request, string $restaurant)
    {
        return $this->setPublished($request, $restaurant, true);
    }

    public function unpublish(Request $request, string $restaurant)
    {
        return $this->setPublished($request, $restaurant, false);
    }

    public function destroy(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'restaurants.delete');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->delete();
        $this->audit->log('restaurant.deleted', $restaurant, ['name' => $restaurant->name, 'slug' => $restaurant->slug], null);

        return redirect()->route('restaurants.index')->with('success', 'Restaurant deleted.');
    }

    // ------------------------------------------------------------------
    // Photos
    // ------------------------------------------------------------------

    public function addMedia(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'restaurants.update');
        $restaurant = $this->resolveRestaurant($restaurant);

        $added = \App\Support\MediaUploads::store($request, $restaurant, 'media/restaurants', allowVideo: false);

        return back()->with('success', $added === 1 ? 'Photo added.' : $added.' photos added.');
    }

    public function setCover(Request $request, string $restaurant, string $media)
    {
        $this->authorizeTo($request, 'restaurants.update');
        $restaurant = $this->resolveRestaurant($restaurant);
        $owned = $restaurant->media()->whereKey($media)->firstOrFail();

        DB::transaction(function () use ($restaurant, $owned): void {
            $restaurant->media()->update(['is_cover' => false]);
            $owned->forceFill(['is_cover' => true])->save();
        });

        return back()->with('success', 'Cover updated.');
    }

    public function destroyMedia(Request $request, string $restaurant, string $media)
    {
        $this->authorizeTo($request, 'restaurants.update');
        $restaurant = $this->resolveRestaurant($restaurant);

        $restaurant->media()->whereKey($media)->firstOrFail()->delete();

        return back()->with('success', 'Photo removed.');
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function setPublished(Request $request, string $slug, bool $publish)
    {
        $this->authorizeTo($request, 'restaurants.publish');
        $restaurant = $this->resolveRestaurant($slug);

        $was = $restaurant->status;
        $publish ? $restaurant->publish() : $restaurant->unpublish();

        $this->audit->log(
            $publish ? 'restaurant.published' : 'restaurant.unpublished',
            $restaurant,
            ['status' => $was],
            ['status' => $restaurant->status],
        );

        return back()->with('success', $publish ? 'Restaurant is live on the marketplace.' : 'Restaurant unpublished.');
    }

    private function formData(?Restaurant $restaurant): array
    {
        return [
            'restaurant' => $restaurant ? $restaurant->only(['name', 'tagline', 'description', 'location_id', 'address_line', 'city', 'region', 'phone', 'email', 'price_level', 'reservations_enabled', 'reservation_duration_minutes', 'delivery_enabled', 'ordering_enabled', 'room_service_enabled', 'tax_inclusive', 'tax_rate']) + [
                'cuisines' => $restaurant->cuisines()->pluck('cuisines.id'),
                'hours' => collect(self::DAYS)->mapWithKeys(fn ($d) => [$d => $restaurant->opening_hours[$d] ?? ''])->all(),
            ] : null,
            'title' => $restaurant?->name,
            'locations' => Location::query()->orderBy('name')->get(['id', 'name'])->map(fn ($l) => [$l->id, $l->name]),
            'cuisines' => Cuisine::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => [$c->id, $c->name]),
            'days' => self::DAYS,
        ];
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'price_level' => ['required', 'integer', 'between:1,4'],
            'reservations_enabled' => ['nullable', 'boolean'],
            'reservation_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'delivery_enabled' => ['nullable', 'boolean'],
            'ordering_enabled' => ['nullable', 'boolean'],
            'room_service_enabled' => ['nullable', 'boolean'],
            'tax_inclusive' => ['nullable', 'boolean'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'cuisines' => ['nullable', 'array'],
            'cuisines.*' => ['integer', 'exists:cuisines,id'],
            'hours' => ['nullable', 'array'],
            'hours.*' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** Validated input → columns; blank opening-hours days are dropped. */
    private function attributes(Request $request, array $validated): array
    {
        $hours = [];
        foreach (self::DAYS as $day) {
            $value = trim((string) ($validated['hours'][$day] ?? ''));
            if ($value !== '') {
                $hours[$day] = $value;
            }
        }

        return collect($validated)->except(['cuisines', 'hours', 'reservation_duration_minutes', 'tax_rate'])->all() + [
            'opening_hours' => $hours ?: null,
            'reservation_duration_minutes' => (int) ($validated['reservation_duration_minutes'] ?? 0) ?: 90,
            'reservations_enabled' => $request->boolean('reservations_enabled'),
            'delivery_enabled' => $request->boolean('delivery_enabled'),
            'ordering_enabled' => $request->boolean('ordering_enabled'),
            'room_service_enabled' => $request->boolean('room_service_enabled'),
            'tax_inclusive' => $request->boolean('tax_inclusive'),
            'tax_rate' => (float) ($validated['tax_rate'] ?? 0),
        ];
    }
}
