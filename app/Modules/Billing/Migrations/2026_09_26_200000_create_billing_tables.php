<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS billing (Phase 27). Plans and module pricing stay in `module_plans`
 * (one Monthly + one Yearly row per module); entitlement stays in
 * `tenant_modules`. These tables add what a business pays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedTinyInteger('percent_off')->nullable();
            $table->unsignedInteger('amount_off_cents')->nullable();
            $table->string('duration')->default('once'); // once | repeating | forever
            $table->unsignedSmallInteger('duration_cycles')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('active'); // active | past_due | cancelled
            $table->string('billing_interval')->default('monthly'); // monthly | yearly
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('billing_coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('coupon_cycles_used')->default(0);
            $table->timestamps();

            $table->index(['status', 'current_period_end']);
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['subscription_id', 'module_id']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->string('source_key')->unique(); // renewal:{sub}:{period start} | change:{sub}:{uuid}
            $table->string('status')->default('open'); // open | paid | void
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->string('coupon_code')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // paymongo | manual | none
            $table->string('payment_reference')->nullable();
            $table->string('checkout_session_id')->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('billing_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_cents');
            $table->unsignedInteger('amount_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_items');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('billing_coupons');
    }
};
