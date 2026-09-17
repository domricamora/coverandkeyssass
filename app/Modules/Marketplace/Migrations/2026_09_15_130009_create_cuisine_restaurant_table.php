<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Restaurant ⇄ cuisine pivot (a restaurant can serve several cuisines). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuisine_restaurant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('cuisine_id')->constrained('cuisines')->cascadeOnDelete();

            $table->unique(['restaurant_id', 'cuisine_id']);
            $table->index('cuisine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuisine_restaurant');
    }
};