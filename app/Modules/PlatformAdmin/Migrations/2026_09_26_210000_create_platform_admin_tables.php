<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Super Admin (Phase 28): CMS pages and platform settings. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_description', 300)->nullable();
            $table->longText('body');
            $table->boolean('is_published')->default(false);
            $table->boolean('in_footer')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('cms_pages');
    }
};
