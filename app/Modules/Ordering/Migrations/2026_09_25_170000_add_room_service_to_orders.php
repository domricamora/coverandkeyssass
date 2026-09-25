<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel room service (Phase 13): an order can be delivered to the room of
 * a checked-in stay at the same business and charged to that stay
 * (payment_method `room_charge`, payment_status `charged`) — the folio
 * (Phase 14) picks those charges up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('user_id')->constrained('bookings')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->after('booking_id')->constrained('rooms')->nullOnDelete();
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('room_service_enabled')->default(false)->after('delivery_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn('room_service_enabled'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
