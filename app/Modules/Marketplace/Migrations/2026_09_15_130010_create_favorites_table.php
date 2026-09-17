<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest wish lists. Polymorphic so a guest can favorite a property or a
 * restaurant (and later rooms/menu items) with one table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('favoritable');
            $table->timestamps();

            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'favorites_user_target_unique');
            $table->index(['favoritable_type', 'favoritable_id'], 'favorites_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};