<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online food ordering (Phase 11).
 *
 * `orders` / `order_items` snapshot names and prices at checkout, so menu
 * edits never rewrite history. Payments and commissions gain a nullable
 * `order_id` beside `booking_id` (exactly one of the two is set), so the
 * existing PayMongo + wallet flow covers food orders too. Promotions gain
 * `applies_to` so a code is either a stay code or an order code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('ordering_enabled')->default(false)->after('delivery_enabled');
            $table->decimal('tax_rate', 5, 2)->default(12)->after('ordering_enabled');
            $table->boolean('tax_inclusive')->default(true)->after('tax_rate');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();

            $table->string('reference', 20)->unique();
            $table->string('status', 20);
            $table->string('fulfillment', 20);      // pickup | delivery
            $table->string('payment_method', 20);   // online | cash
            $table->string('payment_status', 20)->default('unpaid'); // unpaid | paid | refunded

            $table->string('customer_name', 160);
            $table->string('customer_phone', 40)->nullable();
            $table->string('delivery_address', 500)->nullable();
            $table->text('notes')->nullable();

            $table->char('currency', 3)->default('PHP');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('tax_inclusive')->default(true);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('tenant_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('name', 160);
            $table->json('modifiers')->nullable(); // [{group, name, price}]
            $table->decimal('unit_price', 12, 2);  // base + modifiers
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->change();
            $table->foreignId('order_id')->nullable()->after('booking_id')->constrained('orders')->cascadeOnDelete();
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->change();
            $table->foreignId('order_id')->nullable()->after('booking_id')->constrained('orders')->cascadeOnDelete();
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->string('applies_to', 10)->default('stays')->after('property_id');
            $table->foreignId('restaurant_id')->nullable()->after('applies_to')->constrained('restaurants')->cascadeOnDelete();
            $table->decimal('min_subtotal', 12, 2)->nullable()->after('min_nights');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_id');
            $table->dropColumn(['applies_to', 'min_subtotal']);
        });
        Schema::table('commissions', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_id'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_id'));
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn(['ordering_enabled', 'tax_rate', 'tax_inclusive']));
    }
};
