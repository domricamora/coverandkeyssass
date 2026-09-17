<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Property ⇄ amenity pivot (both sides of the marketplace filters). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_amenity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained('amenities')->cascadeOnDelete();

            $table->unique(['property_id', 'amenity_id']);
            $table->index('amenity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_amenity');
    }
};