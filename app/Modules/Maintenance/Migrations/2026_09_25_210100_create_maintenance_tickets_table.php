<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance tickets. Created in Phase 15 so housekeeping can report
 * room issues; the full maintenance workflow (category, cost,
 * attachments, notes) is Phase 16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();

            $table->string('reference', 20)->unique();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal'); // low | normal | high | urgent
            $table->string('status', 20)->default('open');     // open | in_progress | on_hold | resolved | closed
            $table->boolean('room_out_of_order')->default(false);

            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['room_id', 'status']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
