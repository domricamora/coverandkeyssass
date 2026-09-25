<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping (Phase 15): the room's cleanliness status (separate from the
 * Phase 04 sellable `status`) and the cleaning / inspection task queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('housekeeping_status', 20)->default('clean')->after('status');
            $table->timestamp('housekeeping_updated_at')->nullable()->after('housekeeping_status');
            $table->index(['property_id', 'housekeeping_status']);
        });

        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->string('type', 20);                     // checkout_clean | stayover | deep_clean | turndown
            $table->string('status', 20)->default('pending'); // pending | in_progress | completed | cancelled
            $table->string('priority', 10)->default('normal'); // normal | high
            $table->date('due_on');
            $table->text('notes')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->boolean('inspection_passed')->nullable();
            $table->string('inspection_notes', 500)->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status', 'due_on']);
            $table->index(['assigned_to', 'status']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_tasks');
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'housekeeping_status']);
            $table->dropColumn(['housekeeping_status', 'housekeeping_updated_at']);
        });
    }
};
