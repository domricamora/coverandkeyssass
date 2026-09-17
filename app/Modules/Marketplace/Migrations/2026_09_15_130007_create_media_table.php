<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic media (property covers, galleries, restaurant photos — and
 * later rooms, menu items, …).
 *
 * Media carries no `tenant_id`: a row is owned by its parent entity and is
 * only ever loaded through that parent, so tenant isolation and publication
 * rules are inherited from the parent row. Never query media standalone in
 * a public controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');           // mediable_type, mediable_id (+index)
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('alt')->nullable();
            $table->string('caption')->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'sort_order'], 'media_parent_order_index');
            $table->index(['mediable_type', 'mediable_id', 'is_cover'], 'media_parent_cover_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};