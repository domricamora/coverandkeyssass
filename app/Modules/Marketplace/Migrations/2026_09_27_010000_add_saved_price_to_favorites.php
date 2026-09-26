<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Price-drop alerts: the stay's price when it was saved (lowered after each alert). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table): void {
            $table->decimal('saved_price', 12, 2)->nullable()->after('favoritable_id');
        });

        // Existing wish-list stays start from today's price.
        \Illuminate\Support\Facades\DB::table('favorites')->where('favoritable_type', 'property')->whereNull('saved_price')
            ->update(['saved_price' => \Illuminate\Support\Facades\DB::raw('(select base_price from properties where properties.id = favorites.favoritable_id)')]);
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table): void {
            $table->dropColumn('saved_price');
        });
    }
};
