<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews (Phase 24): one verified review per stay / order / table visit
 * (instead of one per listing), category ratings, the room type or dishes
 * reviewed, the owning business (for host screens) and moderation fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->after('user_id')->unique()->constrained('bookings')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('booking_id')->unique()->constrained('orders')->nullOnDelete();
            $table->foreignId('table_reservation_id')->nullable()->after('order_id')->unique()->constrained('table_reservations')->nullOnDelete();
            $table->foreignId('room_type_id')->nullable()->after('table_reservation_id')->constrained('room_types')->nullOnDelete();

            foreach (['cleanliness', 'location', 'service', 'value', 'food', 'amenities'] as $category) {
                $table->unsignedTinyInteger('rating_'.$category)->nullable()->after('rating');
            }

            $table->timestamp('flagged_at')->nullable()->after('published_at');
            $table->string('flag_reason', 255)->nullable()->after('flagged_at');
            $table->foreignId('moderated_by')->nullable()->after('flag_reason')->constrained('users')->nullOnDelete();
            $table->string('moderation_note', 255)->nullable()->after('moderated_by');

            $table->index(['tenant_id', 'status']);
        });

        // A guest may now review each stay / meal, not just each listing once.
        // (New index first: the user_id foreign key needs one while the unique is dropped.)
        Schema::table('reviews', fn (Blueprint $table) => $table->index(['user_id', 'reviewable_type', 'reviewable_id'], 'reviews_user_target_index'));
        Schema::table('reviews', fn (Blueprint $table) => $table->dropUnique('reviews_user_target_unique'));

        DB::statement("UPDATE reviews r JOIN properties p ON r.reviewable_type = 'property' AND r.reviewable_id = p.id SET r.tenant_id = p.tenant_id");
        DB::statement("UPDATE reviews r JOIN restaurants s ON r.reviewable_type = 'restaurant' AND r.reviewable_id = s.id SET r.tenant_id = s.tenant_id");

        Schema::create('review_item_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();

            $table->unique(['review_id', 'menu_item_id']);
            $table->index('menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_item_ratings');

        Schema::table('reviews', fn (Blueprint $table) => $table->unique(['user_id', 'reviewable_type', 'reviewable_id'], 'reviews_user_target_unique'));
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_user_target_index');
            $table->dropIndex(['tenant_id', 'status']);
            foreach (['moderated_by', 'room_type_id', 'table_reservation_id', 'order_id', 'booking_id', 'tenant_id'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn(['rating_cleanliness', 'rating_location', 'rating_service', 'rating_value', 'rating_food', 'rating_amenities', 'flagged_at', 'flag_reason', 'moderation_note']);
        });
    }
};
