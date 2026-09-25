<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM (Phase 21): one contact per guest per business, built from bookings,
 * food orders and table reservations (matched by account, email, phone),
 * with cached activity metrics, tags, notes and a communication log.
 * Booking / order history is read live from those modules, never copied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->string('email', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('source', 20)->default('manual'); // booking | order | reservation | manual
            $table->boolean('is_vip')->default(false);
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('consent_at')->nullable();

            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('reservations_count')->default(0);
            $table->decimal('total_spend', 14, 2)->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'last_activity_at']);
            $table->index(['tenant_id', 'total_spend']);
        });

        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('crm_contact_tag', function (Blueprint $table) {
            $table->foreignId('crm_contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('crm_tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->primary(['crm_contact_id', 'crm_tag_id']);
        });

        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('crm_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);   // email | sms | phone | in_person | chat
            $table->string('direction', 10); // inbound | outbound
            $table->string('subject', 160)->nullable();
            $table->text('body')->nullable();
            $table->string('source_key', 120)->nullable(); // system-logged messages (marketing, Phase 22)
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['crm_contact_id', 'occurred_at']);
            $table->unique(['tenant_id', 'source_key']);
        });
    }

    public function down(): void
    {
        foreach (['crm_interactions', 'crm_notes', 'crm_contact_tag', 'crm_tags', 'crm_contacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
