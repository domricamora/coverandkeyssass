<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Property staff (Phase 04): per-property operational assignments
 * (front desk, housekeeping, maintenance…). Membership is always a subset
 * of the tenant's active members and is enforced in the application; the
 * composite unique guards duplicates at the database level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->string('role', 40)->default('staff'); // manager | front_desk | housekeeping | maintenance | staff
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['property_id', 'user_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_staff');
    }
};
