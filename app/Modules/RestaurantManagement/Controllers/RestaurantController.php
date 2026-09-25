<?php

namespace App\Modules\RestaurantManagement\Controllers;

use App\Modules\Marketplace\Models\Cuisine;
use App\Modules\Marketplace\Models\Location;
use App\Modules\Marketplace\Models\Restaurant;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return view('restaurant-management::restaurants.index', [
            'restaurants' => Restaurant::query()
                ->with('location')
                ->withCount(['menuItems', 'tables'])
                ->latest()
                ->paginate(15),
            'title' => 'Restaurants',
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeTo($request, 'restaurants.create');

        return view('restaurant-management::restaurants.form', $this->formData(new Restaurant) + ['title' => 'New restaurant']);
    }

    public function store(Request $request)
    {
        $this->authorizeTo($request, 'restaurants.create');

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

        return view('restaurant-management::restaurants.show', [
            'restaurant' => $restaurant,
            'title' => $restaurant->name,
        ]);
    }

    public function edit(Request $request, string $restaurant)
    {
        $this->authorizeTo($request, 'restaurants.update');
        $restaurant = $this->resolveRestaurant($restaurant);

        return view('restaurant-management::restaurants.form', $this->formData($restaurant) + [
            'photos' => $restaurant->media()->where('kind', 'image')->get(),
            'title' => 'Edit — '.$restaurant->name,
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

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'alt' => ['nullable', 'string', 'max:200'],
        ]);

        $count = $restaurant->media()->count();

        $restaurant->media()->create([
            'disk' => 'public',
            'path' => $validated['url'],
            'kind' => 'image',
            'alt' => $validated['alt'] ?? null,
            'is_cover' => $count === 0,
            'sort_order' => $count + 1,
        ]);

        return back()->with('success', 'Photo added.');
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

    private function formData(Restaurant $restaurant): array
    {
        return [
            'restaurant' => $restaurant,
            'locations' => Location::query()->orderBy('name')->get(),
            'cuisines' => Cuisine::query()->orderBy('name')->get(),
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
            'delivery_enabled' => ['nullable', 'boolean'],
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

        return collect($validated)->except(['cuisines', 'hours'])->all() + [
            'opening_hours' => $hours ?: null,
            'reservations_enabled' => $request->boolean('reservations_enabled'),
            'delivery_enabled' => $request->boolean('delivery_enabled'),
        ];
    }
}
