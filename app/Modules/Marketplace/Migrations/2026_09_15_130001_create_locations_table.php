<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference data: destinations the marketplace is browsable by.
 * Locations are platform-owned (not tenant-owned) so every tenant can be
 * listed under a shared destination page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('region')->nullable();
            $table->string('country_code', 2)->default('PH');
            $table->string('country')->default('Philippines');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('properties_count')->default(0);
            $table->unsignedInteger('restaurants_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_featured', 'sort_order']);
            $table->index(['country_code', 'region']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};