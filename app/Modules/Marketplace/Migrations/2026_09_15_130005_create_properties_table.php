<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned properties (hotels, resorts, B&Bs, rentals…).
 *
 * Only rows with status = 'published' are ever visible to the public
 * marketplace; draft/pending/suspended rows stay private to the host.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->nullOnDelete();

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

            $table->unsignedSmallInteger('max_guests')->default(2);
            $table->unsignedSmallInteger('bedrooms')->default(1);
            $table->unsignedSmallInteger('beds')->default(1);
            $table->unsignedSmallInteger('bathrooms')->default(1);

            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('weekend_price', 12, 2)->nullable();
            $table->decimal('cleaning_fee', 12, 2)->default(0);
            $table->char('currency', 3)->default('PHP');

            $table->string('check_in_time', 5)->default('14:00');
            $table->string('check_out_time', 5)->default('11:00');
            $table->json('highlights')->nullable();
            $table->json('policies')->nullable();

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
            $table->index(['status', 'property_type_id']);
            $table->index(['city', 'country_code']);
            $table->index('base_price');
            $table->index('avg_rating');
            $table->index('published_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};