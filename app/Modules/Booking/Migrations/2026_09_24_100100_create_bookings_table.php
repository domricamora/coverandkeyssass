<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings (Phase 05): one reservation (possibly multi-room / group) for a
 * stay [check_in, check_out) — check_out is the departure day, not a night.
 * `user_id` is the marketplace customer (null for walk-ins / manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();

            $table->string('source', 20); // walk_in | manual | marketplace
            $table->string('status', 20);
            $table->string('group_name', 120)->nullable();

            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);

            $table->string('guest_name', 120);
            $table->string('guest_email')->nullable();
            $table->string('guest_phone', 40)->nullable();
            $table->text('special_requests')->nullable();

            $table->string('currency', 3)->default('PHP');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['property_id', 'check_in']);
        });

        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types');
            $table->foreignId('room_id')->constrained('rooms');
            $table->json('nightly_rates'); // {"2030-08-01": "5000.00", ...}
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        // Occupied inventory. One row per room per night while the booking
        // holds the room; the unique index makes a double booking impossible
        // at the database level, whatever the application does.
        Schema::create('room_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_room_id')->constrained('booking_rooms')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('night');

            $table->unique(['room_id', 'night']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_nights');
        Schema::dropIfExists('booking_rooms');
        Schema::dropIfExists('bookings');
    }
};
