<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point of sale (Phase 19): dine-in orders on tables (`orders.channel =
 * pos`), cash sessions (float → count → variance) and the payments /
 * refunds taken at the register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel', 10)->default('online')->after('reference'); // online | pos
            $table->foreignId('restaurant_table_id')->nullable()->after('room_id')->constrained('restaurant_tables')->nullOnDelete();
            $table->string('discount_reason', 160)->nullable()->after('discount_total');
            $table->index(['restaurant_id', 'channel', 'status']);
        });

        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'closed_at']);
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->restrictOnDelete();
            $table->string('method', 20);            // cash | card | ewallet | room_charge
            $table->decimal('amount', 12, 2);         // negative = refund
            $table->decimal('tendered', 12, 2)->nullable();
            $table->decimal('change_given', 12, 2)->nullable();
            $table->string('reference', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pos_session_id', 'method']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_sessions');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'channel', 'status']);
            $table->dropConstrainedForeignId('restaurant_table_id');
            $table->dropColumn(['channel', 'discount_reason']);
        });
    }
};
