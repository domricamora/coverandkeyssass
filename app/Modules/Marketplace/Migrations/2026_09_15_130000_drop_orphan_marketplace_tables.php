<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance migration for the dev database only.
 *
 * An earlier, uncommitted marketplace attempt (2026_09_11_083306..083316)
 * created `listings`, `listing_images`, `favorites`, `amenities`,
 * `amenity_listing` and `reviews` in `hospitality_os`, but its migration
 * files were never committed and are gone. Phase 03 replaces that ad-hoc
 * schema with the authoritative one defined in this module, so the orphan
 * tables are removed first.
 *
 * Safety: the migration aborts (rather than dropping) when any orphan table
 * holds rows, so no real data can be destroyed by running it.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $orphans = [
        // Children first: `favorites`, `reviews` and `listing_images`
        // reference `listings`, so they must go before their parent.
        'amenity_listing',
        'listing_images',
        'reviews',
        'favorites',
        'listings',
        'amenities',
    ];

    /** @var list<string> */
    private array $orphanMigrations = [
        '2026_09_11_081554_add_timezone_to_users_table',
        '2026_09_11_083306_create_listings_table',
        '2026_09_11_083307_create_listing_images_table',
        '2026_09_11_083314_create_favorites_table',
        '2026_09_11_083315_create_amenities_table',
        '2026_09_11_083316_create_reviews_table',
    ];

    public function up(): void
    {
        foreach ($this->orphans as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = (int) DB::table($table)->count();

            if ($rows > 0) {
                throw new RuntimeException(
                    "Refusing to drop orphan table [{$table}]: it holds {$rows} rows. ".
                    'Export the data and prune it manually before running this migration.'
                );
            }

            Schema::drop($table);
        }

        DB::table('migrations')->whereIn('migration', $this->orphanMigrations)->delete();
    }

    public function down(): void
    {
        // The orphan tables were ad-hoc and carried no data; there is nothing
        // to restore. Phase 03 tables are dropped by their own migrations.
    }
};