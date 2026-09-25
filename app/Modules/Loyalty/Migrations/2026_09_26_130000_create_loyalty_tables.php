<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loyalty (Phase 23): a points programme per business on top of CRM
 * contacts — an append-only points ledger (idempotent by source key),
 * tiers, rewards, referrals — and gift cards, which also carry guests'
 * store credit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->decimal('pesos_per_point', 10, 2)->default(100); // ₱100 spending = 1 point
            $table->unsignedInteger('referral_points')->default(200);  // to both guests
            $table->timestamps();
        });

        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->integer('points_balance')->default(0);
            $table->unsignedInteger('lifetime_points')->default(0);
            $table->string('tier', 20)->default('bronze');
            $table->string('referral_code', 20);
            $table->foreignId('referred_by_id')->nullable()->constrained('loyalty_accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'crm_contact_id']);
            $table->unique(['tenant_id', 'referral_code']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('loyalty_account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
            $table->string('type', 20); // earn | reversal | redeem | referral | adjust
            $table->integer('points');   // signed
            $table->integer('balance_after');
            $table->string('description', 255);
            $table->string('source_key', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'source_key']);
            $table->index(['loyalty_account_id', 'created_at']);
        });

        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 160);
            $table->unsignedInteger('points_cost');
            $table->string('kind', 10); // coupon | credit
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->decimal('credit_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('kind', 10)->default('gift');  // gift (sold) | credit (earned / goodwill)
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->decimal('initial_value', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->string('sold_via', 10)->nullable(); // cash | bank — null for credits
            $table->date('expires_on')->nullable();
            $table->string('status', 10)->default('active'); // active | void
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('gift_card_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reference', 60); // OR… / BK…
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['gift_card_redemptions', 'gift_cards', 'loyalty_rewards', 'loyalty_transactions', 'loyalty_accounts', 'loyalty_programs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
