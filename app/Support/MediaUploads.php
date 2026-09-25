<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Photo / video intake for listings (properties and restaurants): uploaded
 * files (several at once) or a hosted URL, each tagged with a tour area
 * ("Rooms", "Pool", "Dining room"…) stored in media.caption. The public
 * listing page groups the gallery into a guest tour by that area.
 */
class MediaUploads
{
    /** Suggested tour areas; hosts may type their own. */
    public const AREAS = [
        'property' => ['Exterior', 'Rooms', 'Bathrooms', 'Lobby', 'Pool', 'Breakfast', 'Dining', 'Spa', 'Views', 'Grounds'],
        'restaurant' => ['Dining room', 'Dishes', 'Bar', 'Kitchen', 'Terrace', 'Private dining', 'Exterior'],
    ];

    /** @return int number of media rows created */
    public static function store(Request $request, Model $listing, string $folder, bool $allowVideo = true): int
    {
        $validated = $request->validate([
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=400,min_height=300'],
            'url' => ['nullable', 'url', 'max:500'],
            'kind' => ['nullable', $allowVideo ? 'in:image,video' : 'in:image'],
            'alt' => ['nullable', 'string', 'max:200'],
            'caption' => ['nullable', 'string', 'max:60'],
        ]);

        if (empty($validated['photos']) && empty($validated['url'])) {
            throw ValidationException::withMessages(['photos' => 'Choose at least one photo or paste a link.']);
        }

        $caption = $validated['caption'] ?? null;
        $items = [];

        foreach ($request->file('photos', []) as $file) {
            $items[] = ['path' => self::storeAsWebp($file, $folder.'/'.$listing->getKey()), 'kind' => 'image'];
        }

        if (! empty($validated['url'])) {
            $items[] = ['path' => $validated['url'], 'kind' => $validated['kind'] ?? 'image'];
        }

        $hasCover = $listing->media()->where('is_cover', true)->exists();
        $order = (int) $listing->media()->max('sort_order');

        foreach ($items as $i => $item) {
            $listing->media()->create([
                'disk' => 'public',
                'path' => $item['path'],
                'kind' => $item['kind'],
                'alt' => $validated['alt'] ?? ($caption ? $listing->name.' · '.$caption : $listing->name),
                'caption' => $caption,
                'is_cover' => ! $hasCover && $i === 0 && $item['kind'] === 'image',
                'sort_order' => $order + $i + 1,
            ]);
        }

        return count($items);
    }

    /**
     * Re-encode an upload as WebP (GD): upright per EXIF, at most 2000 px on
     * the long side, quality 82. Re-encoding also drops EXIF (GPS etc.).
     * Falls back to the original file only if GD cannot decode it.
     */
    public static function storeAsWebp(UploadedFile $file, string $dir): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($image === false || ! function_exists('imagewebp')) {
            return $file->store($dir, 'public');
        }

        $orientation = function_exists('exif_read_data') && in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)
            ? (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1)
            : 1;
        $image = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, 2000 / max($w, $h));

        if ($scale < 1) {
            $image = imagescale($image, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC);
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, 82);
        $bytes = (string) ob_get_clean();

        $path = $dir.'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }
}
