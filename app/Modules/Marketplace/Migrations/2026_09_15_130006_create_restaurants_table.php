<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned restaurant listings (marketplace surface).
 *
 * Menus, tables and orders arrive in Phases 09–11; this table is the
 * directory entry the marketplace and its filters need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();

            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country_code', 2)->default('PH');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('price_level')->default(2);
            $table->json('opening_hours')->nullable();
            $table->json('highlights')->nullable();

            $table->boolean('reservations_enabled')->default(false);
            $table->boolean('delivery_enabled')->default(false);

            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('favorites_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['status', 'is_featured']);
            $table->index(['location_id', 'status']);
            $table->index(['city', 'country_code']);
            $table->index('price_level');
            $table->index('avg_rating');
            $table->index('published_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};