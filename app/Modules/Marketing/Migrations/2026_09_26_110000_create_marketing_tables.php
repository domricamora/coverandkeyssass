<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing (Phase 22): email / SMS campaigns to CRM segments (consented
 * contacts only), personal single-use coupons on top of promotions, saved
 * carts for abandoned-cart follow-ups, and per-business automations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('channel', 10);          // email | sms
            $table->string('audience', 40);         // all | segment:<key> | tag:<id>
            $table->string('subject', 160)->nullable();
            $table->text('body');
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete(); // personal coupons from this promo
            $table->string('status', 10)->default('draft'); // draft | scheduled | sending | sent
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'scheduled_at']);
        });

        Schema::create('marketing_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('marketing_campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('address', 160);
            $table->string('coupon_code', 40)->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['marketing_campaign_id', 'crm_contact_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('code', 40);
            $table->timestamp('used_at')->nullable();
            $table->string('used_on', 40)->nullable(); // BK… / OR… reference
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('saved_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->json('lines');
            $table->timestamps();

            $table->unique(['user_id', 'restaurant_id']);
        });

        Schema::create('marketing_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 30); // abandoned_booking | abandoned_cart | review_request | post_stay | reactivation
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('delay_hours')->default(24);
            $table->string('subject', 160);
            $table->text('body');
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        foreach (['marketing_automations', 'saved_carts', 'coupons', 'marketing_recipients', 'marketing_campaigns'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
