<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications (Phase 26): per-user channel preferences per event, and a
 * push-ready architecture — registered devices plus an outbox a future
 * FCM / APNs worker drains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('channel', 10); // mail | sms | push   (in-app is always on)
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'event', 'channel']);
        });

        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 10); // ios | android | web
            $table->string('token', 255)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('title', 160);
            $table->string('body', 500);
            $table->json('data')->nullable();
            $table->timestamp('sent_at')->nullable(); // set by the delivery worker
            $table->timestamps();

            $table->index(['sent_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_messages');
        Schema::dropIfExists('push_devices');
        Schema::dropIfExists('notification_preferences');
    }
};
