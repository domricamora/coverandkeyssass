<?php

namespace App\Http\Controllers;

use App\Modules\PropertyManagement\Models\RoomType;
use App\Modules\RestaurantManagement\Models\MenuCategory;
use App\Modules\RestaurantManagement\Models\MenuItem;
use App\Support\MediaUploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Photos for things inside a listing (Booking.com / Agoda style room-type
 * galleries, dish photos, menu section banners), on the same WebP media
 * pipeline as listing photos. Rows are looked up inside the active business
 * (tenant scope), so another business's id is a 404.
 */
class PhotoController extends Controller
{
    /** route key => [model, permission, storage folder] */
    private const OWNERS = [
        'room-type' => [RoomType::class, 'rooms.update', 'media/room-types'],
        'menu-item' => [MenuItem::class, 'menu.manage', 'media/menu'],
        'menu-category' => [MenuCategory::class, 'menu.manage', 'media/menu'],
    ];

    public function store(Request $request, string $type, int $id)
    {
        $owner = $this->owner($request, $type, $id);
        $added = MediaUploads::store($request, $owner, self::OWNERS[$type][2], allowVideo: false);

        return back()->with('success', $added === 1 ? 'Photo added.' : $added.' photos added.');
    }

    public function cover(Request $request, string $type, int $id, int $media)
    {
        $owner = $this->owner($request, $type, $id);
        $photo = $owner->media()->whereKey($media)->firstOrFail();

        DB::transaction(function () use ($owner, $photo): void {
            $owner->media()->update(['is_cover' => false]);
            $photo->forceFill(['is_cover' => true])->save();
        });

        return back()->with('success', 'Main photo updated.');
    }

    public function destroy(Request $request, string $type, int $id, int $media)
    {
        $this->owner($request, $type, $id)->media()->whereKey($media)->firstOrFail()->delete();

        return back()->with('success', 'Photo removed.');
    }

    /** @return array{photos: list<array<string, mixed>>, store: string} for the React photo strip */
    public static function payload(Model $owner, string $type): array
    {
        return [
            'photos' => MediaUploads::payload($owner, fn ($m) => [
                'make_cover' => route('photos.cover', [$type, $owner->getKey(), $m->id]),
                'destroy' => route('photos.destroy', [$type, $owner->getKey(), $m->id]),
            ])['photos'],
            'store' => route('photos.store', [$type, $owner->getKey()]),
        ];
    }

    private function owner(Request $request, string $type, int $id): Model
    {
        abort_unless(isset(self::OWNERS[$type]), 404);
        [$class, $permission] = self::OWNERS[$type];
        abort_unless($request->user()->hasPermissionTo($permission), 403);

        return $class::query()->findOrFail($id); // tenant-scoped: only the active business's rows
    }
}
