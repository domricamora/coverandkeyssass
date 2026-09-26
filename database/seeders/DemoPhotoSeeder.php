<?php

namespace Database\Seeders;

use App\Modules\PropertyManagement\Models\RoomType;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * Demo photos for room types (Booking.com-style galleries), dishes and menu
 * sections, reusing the Unsplash tour images in public/img/demo/tour.
 * Idempotent: anything that already has photos is left alone.
 */
class DemoPhotoSeeder extends Seeder
{
    public function run(): void
    {
        $tour = collect(glob(public_path('img/demo/tour/*.jpg')))->map(fn ($f) => 'img/demo/tour/'.basename($f));
        $pick = fn (string $prefix) => $tour->filter(fn ($p) => str_starts_with(basename($p), $prefix.'-'))->values();

        [$rooms, $baths, $pools, $dishes, $dining] = [$pick('room'), $pick('bath'), $pick('pool'), $pick('dish'), $pick('dining')];

        if ($rooms->isEmpty() || $dishes->isEmpty()) {
            return; // demo images not present
        }

        $attach = function ($owner, array $paths, string $caption): void {
            foreach (array_values($paths) as $i => $path) {
                $owner->media()->create([
                    'disk' => 'public', 'path' => $path, 'kind' => 'image', 'caption' => $caption,
                    'alt' => $owner->name.' - '.$caption, 'is_cover' => $i === 0, 'sort_order' => $i + 1,
                ]);
            }
        };

        RoomType::query()->withoutGlobalScopes()->whereDoesntHave('media')->orderBy('id')->get()
            ->each(fn (RoomType $t, int $i) => $attach($t, [
                $rooms[$i % $rooms->count()],
                $rooms[($i + 3) % $rooms->count()],
                $baths->isNotEmpty() ? $baths[$i % $baths->count()] : $rooms[($i + 1) % $rooms->count()],
                $pools->isNotEmpty() ? $pools[$i % $pools->count()] : $rooms[($i + 2) % $rooms->count()],
            ], 'Room'));

        MenuItem::query()->withoutGlobalScopes()->whereDoesntHave('media')->whereNull('photo_url')->orderBy('id')->get()
            ->each(fn (MenuItem $item, int $i) => $attach($item, [$dishes[$i % $dishes->count()]], 'Dish'));

        MenuCategory::query()->withoutGlobalScopes()->whereDoesntHave('media')->orderBy('id')->get()
            ->each(fn (MenuCategory $c, int $i) => $attach($c, [$dining->isNotEmpty() ? $dining[$i % $dining->count()] : $dishes[$i % $dishes->count()]], 'Section'));
    }
}
