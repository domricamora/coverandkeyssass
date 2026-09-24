<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room types (Phase 04): the pricing/occupancy template a property sells.
 *
 * Property → Room Type → Room. Room types are tenant-owned rows reached
 * through their property; prices can be overridden per date range through
 * the rate_periods table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

            $table->string('name', 120);
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('max_guests')->default(2);
            $table->unsignedSmallInteger('beds')->default(1);
            $table->string('bed_configuration', 120)->nullable();
            $table->unsignedInteger('size_sqm')->nullable();

            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('weekend_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->unsignedSmallInteger('min_stay_nights')->default(1);

            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'sort_order']);
            $table->index(['property_id', 'name']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
