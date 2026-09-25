<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory (Phase 18) for hotels and restaurants: catalogue, stock per
 * location, an append-only movements ledger, suppliers + purchase orders,
 * and menu-item recipes so food sales consume stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('inventory_category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('sku', 40);
            $table->string('name', 160);
            $table->string('unit', 10);                       // see App\Modules\Inventory\Support\Unit
            $table->decimal('cost_per_unit', 12, 4)->default(0); // weighted average
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'stock_location_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->string('type', 20); // receipt | issue | adjustment | transfer_in | transfer_out | waste | sale | sale_return
            $table->decimal('quantity', 14, 3);      // signed
            $table->decimal('balance_after', 14, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('source_key', 120)->nullable()->unique(); // idempotency for derived movements (orders)
            $table->string('notes', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['tenant_id', 'type', 'created_at']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('status', 20)->default('draft'); // draft | ordered | partially_received | received | cancelled
            $table->date('expected_on')->nullable();
            $table->string('notes', 500)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 4);
            $table->timestamps();
        });

        Schema::create('menu_item_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3); // in the inventory item's unit
            $table->string('entered_unit', 10)->nullable();
            $table->decimal('entered_quantity', 14, 3)->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'inventory_item_id']);
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->foreignId('stock_location_id')->nullable()->after('prep_minutes')->constrained('stock_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropConstrainedForeignId('stock_location_id'));
        Schema::dropIfExists('menu_item_ingredients');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('inventory_categories');
    }
};
