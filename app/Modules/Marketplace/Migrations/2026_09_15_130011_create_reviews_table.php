<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest reviews. One review per user per subject; moderation status gates
 * public visibility ('published' only) and the denormalised rating columns
 * on the reviewed row are recalculated from published reviews.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->morphs('reviewable');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();

            $table->string('status', 20)->default('pending');
            $table->text('host_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'reviewable_type', 'reviewable_id'], 'reviews_user_target_unique');
            $table->index(['reviewable_type', 'reviewable_id', 'status'], 'reviews_target_status_index');
            $table->index('rating');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};