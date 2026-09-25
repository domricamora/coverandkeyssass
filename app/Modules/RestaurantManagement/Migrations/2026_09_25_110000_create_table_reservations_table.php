<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant reservations (Phase 10): a party seated at one table for
 * [reserved_at, ends_at). Overbooking is prevented in ReservationService
 * by locking the restaurant's tables before the overlap check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->unsignedSmallInteger('reservation_duration_minutes')->default(90)->after('reservations_enabled');
        });

        Schema::create('table_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reference', 20)->unique();
            $table->string('source', 20);
            $table->string('status', 20);

            $table->dateTime('reserved_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('party_size');

            $table->string('guest_name', 160);
            $table->string('guest_email', 160)->nullable();
            $table->string('guest_phone', 40)->nullable();
            $table->text('special_requests')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'reserved_at']);
            $table->index(['restaurant_table_id', 'reserved_at', 'ends_at']);
            $table->index(['user_id', 'reserved_at']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_reservations');

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('reservation_duration_minutes');
        });
    }
};
