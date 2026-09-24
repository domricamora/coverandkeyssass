<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 04: media kind. The polymorphic media table now distinguishes
 * photos from videos so a listing can carry a promo video alongside its
 * gallery. Existing rows default to `image` (additive, backfill-free).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('kind', 20)->default('image')->after('caption');

            $table->index(['mediable_type', 'mediable_id', 'kind'], 'media_parent_kind_index');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_parent_kind_index');
            $table->dropColumn('kind');
        });
    }
};
