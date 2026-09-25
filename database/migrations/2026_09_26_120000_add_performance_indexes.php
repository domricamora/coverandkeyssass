<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 33 — indexes for the access paths added by the performance pass:
 * incremental CRM / accounting syncs read "this business, changed since X",
 * and the booking desk lists a business's stays ordered by check-in.
 */
return new class extends Migration
{
    private const INDEXES = [
        'bookings' => [['tenant_id', 'updated_at'], ['tenant_id', 'check_in']],
        'orders' => [['tenant_id', 'updated_at']],
        'payments' => [['tenant_id', 'updated_at']],
        'table_reservations' => [['tenant_id', 'updated_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $columns) {
                    $blueprint->index($columns);
                }
            });
        }
    }

    /**
     * MySQL drops a foreign key's auto-generated index once a composite index
     * can serve it (payments.tenant_id did), after which the composite can't
     * be dropped. So restore a plain tenant_id index first, and skip indexes
     * that are already gone (DDL is not transactional; a failed run is partial).
     */
    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasIndex($table, ['tenant_id'])) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index('tenant_id'));
            }

            foreach ($indexes as $columns) {
                if (Schema::hasIndex($table, $columns)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($columns));
                }
            }
        }
    }
};
