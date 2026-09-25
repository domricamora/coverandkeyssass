<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery (Phase 12): zones per restaurant, the business's drivers, and
 * the delivery fields on orders (zone, driver, drop-off point, schedule,
 * ETA, dispatch / delivered times).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('radius_km', 6, 2)->nullable(); // null = named area, no distance check
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('min_order', 12, 2)->nullable();
            $table->decimal('free_over', 12, 2)->nullable();
            $table->unsignedSmallInteger('eta_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['restaurant_id', 'name']);
            $table->index('tenant_id');
        });

        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('vehicle', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_zone_id')->nullable()->after('delivery_address')->constrained('delivery_zones')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->after('delivery_zone_id')->constrained('delivery_drivers')->nullOnDelete();
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('driver_id');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
            $table->dateTime('scheduled_for')->nullable()->after('notes');
            $table->dateTime('estimated_at')->nullable()->after('scheduled_for');
            $table->timestamp('dispatched_at')->nullable()->after('ready_at');
            $table->timestamp('delivered_at')->nullable()->after('dispatched_at');

            $table->index(['driver_id', 'status']);
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->unsignedSmallInteger('prep_minutes')->default(20)->after('tax_inclusive');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn('prep_minutes'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['driver_id', 'status']);
            $table->dropConstrainedForeignId('driver_id');
            $table->dropConstrainedForeignId('delivery_zone_id');
            $table->dropColumn(['delivery_lat', 'delivery_lng', 'scheduled_for', 'estimated_at', 'dispatched_at', 'delivered_at']);
        });
        Schema::dropIfExists('delivery_drivers');
        Schema::dropIfExists('delivery_zones');
    }
};
