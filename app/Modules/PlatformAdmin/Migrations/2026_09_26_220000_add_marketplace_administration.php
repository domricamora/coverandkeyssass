<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace administration (Phase 29): featured / sponsored placement,
 * a manual ranking boost, verification of listings and hosts, and
 * guest reports of listings.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['properties', 'restaurants'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('featured_until')->nullable()->after('is_featured');
                $table->timestamp('sponsored_until')->nullable()->after('featured_until');
                $table->smallInteger('ranking_boost')->default(0)->after('sponsored_until');
                $table->timestamp('verified_at')->nullable()->after('ranking_boost');
                $table->index('sponsored_until');
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_note', 250)->nullable();
        });

        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('reportable'); // property | restaurant
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open'); // open | resolved | dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 250)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');

        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['verified_at', 'verification_note']));

        foreach (['properties', 'restaurants'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['sponsored_until']);
                $table->dropColumn(['featured_until', 'sponsored_until', 'ranking_boost', 'verified_at']);
            });
        }
    }
};
