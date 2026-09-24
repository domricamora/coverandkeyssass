<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rate periods (Phase 04): date-range pricing overrides per room type
 * (high season, holidays, promos). Overlapping ranges for the same room
 * type are rejected in the application; the composite index supports the
 * overlap lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();

            $table->string('name', 120)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('nightly_price', 12, 2);
            $table->decimal('weekend_nightly_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('min_stay_nights')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['room_type_id', 'start_date', 'end_date']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_periods');
    }
};
