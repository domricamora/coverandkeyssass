<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Availability blocks (Phase 04): maintenance windows and owner blocks that
 * take a room type (or a single room inside it) off the market for an
 * inclusive date range. The booking engine (Phase 05) consumes these rows
 * when resolving sellable inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 40)->default('maintenance'); // maintenance | owner_block | event
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['property_id', 'start_date', 'end_date']);
            $table->index(['room_type_id', 'start_date', 'end_date']);
            $table->index('room_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
    }
};
