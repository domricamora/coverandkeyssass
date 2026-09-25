<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest folio (Phase 14): one ledger per booking of charges, payments and
 * refunds. Entries derived from other records (room nights, discount,
 * online payments, room-service orders) carry a `source_key` that is
 * unique per booking, so re-syncing never posts anything twice. Entries
 * are never deleted — a mistake is voided (voided_at + reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            $table->string('type', 10);       // charge | payment | refund
            $table->string('category', 20);   // room, food, room_service, laundry, minibar, activities, transport, other / cash, card, transfer, ewallet, online
            $table->string('description', 255);
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('amount', 12, 2); // quantity × unit (negative for discounts)
            $table->date('service_date')->nullable();
            $table->string('reference', 120)->nullable();

            $table->string('source_key', 80)->nullable(); // e.g. night:12:2030-09-05, order:7, payment:3
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'source_key']);
            $table->index(['booking_id', 'type']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_entries');
    }
};
