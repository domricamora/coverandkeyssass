<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments (Phase 07, PayMongo).
 *
 * `payments`: one checkout attempt for a booking. Provider ids are unique so
 * the same PayMongo checkout / payment can never be recorded twice.
 * `payment_events`: every webhook event by PayMongo event id (unique) — the
 * idempotency ledger; a replayed event is acknowledged and ignored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('provider', 20)->default('paymongo');
            $table->string('checkout_session_id')->nullable()->unique();
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 20)->default('pending'); // pending | paid | failed | refunded
            $table->string('method', 30)->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('refund_id')->nullable()->unique();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('type', 80);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
