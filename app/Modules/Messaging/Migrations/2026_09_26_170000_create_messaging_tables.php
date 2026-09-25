<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messaging (Phase 25): threads between a guest and a business (host /
 * restaurant), a user and platform support, or staff inside a business;
 * messages with private attachments (media, local disk); read status per
 * participant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete(); // null = platform support
            $table->string('kind', 20); // guest_host | guest_restaurant | support | staff
            $table->string('subject', 160);
            $table->foreignId('guest_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('about'); // booking / order / table reservation / property / restaurant
            $table->string('status', 10)->default('open'); // open | closed
            $table->timestamp('last_message_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'kind', 'last_message_at']);
            $table->index(['guest_user_id', 'last_message_at']);
        });

        Schema::create('message_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('side', 10); // guest | business | platform | staff
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_thread_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_thread_id')->constrained('message_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('side', 10);
            $table->text('body');
            $table->timestamps();

            $table->index(['message_thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_participants');
        Schema::dropIfExists('message_threads');
    }
};
