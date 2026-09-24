<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical room inventory (Phase 04): the concrete rentable units behind a
 * room type ("Deluxe Room" → 101, 102, 103).
 *
 * Room numbers are unique per property while the row is alive — enforced in
 * the application (soft deletes would otherwise block number reuse), backed
 * here with a composite index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();

            $table->string('room_number', 20);
            $table->string('name', 120)->nullable();
            $table->unsignedSmallInteger('floor')->nullable();
            $table->string('status', 20)->default('active'); // active | maintenance | inactive
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Uniqueness of (property, room_number) among live rows is
            // enforced in the application — a MySQL unique index that
            // includes the nullable deleted_at would not fire (NULLs are
            // never equal), so we only index here.
            $table->index(['property_id', 'room_number']);
            $table->index(['room_type_id', 'status']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
