<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('password');
            $table->string('status')->default('active')->index()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->softDeletes()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'status', 'last_login_at']);
            $table->dropSoftDeletes();
        });
    }
};
