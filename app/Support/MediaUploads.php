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

    /**
     * Photos + videos of a listing for the React media manager; $urls maps a
     * media row to its per-item action URLs (make_cover, destroy).
     *
     * @return array{photos: list<array<string, mixed>>, videos: list<array<string, mixed>>}
     */
    public static function payload(Model $listing, callable $urls): array
    {
        $row = fn ($m) => ['id' => $m->id, 'url' => $m->url(), 'alt' => $m->alt, 'caption' => $m->caption, 'cover' => (bool) $m->is_cover] + $urls($m);

        return [
            'photos' => $listing->media()->where('kind', 'image')->ordered()->get()->map($row)->all(),
            'videos' => $listing->media()->where('kind', 'video')->ordered()->get()->map($row)->all(),
        ];
    }

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
        // A decoded 24 MP phone photo is ~96 MB in GD; give image work room without raising it app-wide.
        if (self::bytes((string) ini_get('memory_limit')) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }

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
            $scaled = imagescale($image, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC);
            unset($image); // free the full-size bitmap before encoding
            $image = $scaled;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, 82);
        $bytes = (string) ob_get_clean();

        unset($image);
        $path = $dir.'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /** php.ini size ("128M", "-1") → bytes; -1 means unlimited. */
    private static function bytes(string $value): int
    {
        if ($value === '-1') {
            return PHP_INT_MAX;
        }

        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
