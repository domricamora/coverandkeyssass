<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance (Phase 16): category, cost and lifecycle times on tickets,
 * plus a notes thread. Attachments use the polymorphic `media` table
 * (private `local` disk, served through an authorised download route).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->string('category', 20)->default('other')->after('description');
            $table->decimal('cost', 12, 2)->nullable()->after('room_out_of_order');
            $table->timestamp('started_at')->nullable()->after('assigned_to');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
            $table->index(['tenant_id', 'status', 'priority']);
        });

        Schema::create('maintenance_ticket_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('maintenance_ticket_id')->constrained('maintenance_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('maintenance_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_ticket_notes');
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'priority']);
            $table->dropColumn(['category', 'cost', 'started_at', 'closed_at']);
        });
    }
};
