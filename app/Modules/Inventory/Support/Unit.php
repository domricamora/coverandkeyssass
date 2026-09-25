<?php

namespace App\Modules\Inventory\Support;

use InvalidArgumentException;

/**
 * Stock units and conversions. Quantities are stored in the item's own
 * unit; a recipe may enter "150 g" for beef stocked in kg and it is
 * converted once, on save.
 */
final class Unit
{
    /** unit => [dimension, factor to the dimension's base (pc, g, ml)] */
    public const UNITS = [
        'pc' => ['count', 1],
        'dozen' => ['count', 12],
        'pack' => ['count', 1],
        'bottle' => ['count', 1],
        'g' => ['mass', 1],
        'kg' => ['mass', 1000],
        'ml' => ['volume', 1],
        'l' => ['volume', 1000],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::UNITS);
    }

    /** Units a quantity entered for an item in $unit may use. @return list<string> */
    public static function compatibleWith(string $unit): array
    {
        $dimension = self::UNITS[$unit][0] ?? null;

        // pack / bottle are opaque counts: only themselves.
        if (in_array($unit, ['pack', 'bottle'], true)) {
            return [$unit];
        }

        return array_values(array_filter(self::all(), fn ($u) => self::UNITS[$u][0] === $dimension && ! in_array($u, ['pack', 'bottle'], true)));
    }

    public static function convert(float $quantity, string $from, string $to): float
    {
        if ($from === $to) {
            return $quantity;
        }

        if (! in_array($from, self::compatibleWith($to), true)) {
            throw new InvalidArgumentException("Cannot convert {$from} to {$to}.");
        }

        return $quantity * self::UNITS[$from][1] / self::UNITS[$to][1];
    }
}
