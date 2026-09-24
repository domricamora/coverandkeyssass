<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Host wallet & commissions (Phase 08).
 *
 * commission_rates: platform-level (no tenant). kind `global` | `listing`
 *   (rateable = property/restaurant) | `promotional` (date window, optional
 *   rateable). Resolved by CommissionService.
 * commissions: one per paid payment (unique payment_id) — the split of the
 *   gross between platform fee and host earning.
 * wallets: one per business; `pending_balance` = earnings of stays not yet
 *   checked out, `available_balance` = withdrawable (may go negative after a
 *   refund of released earnings; offset by later earnings).
 * wallet_transactions: append-only ledger; per bucket, the sum of amounts
 *   equals the wallet balance.
 * payouts: host withdrawal requests, settled manually by the Super Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rates', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // global | listing | promotional
            $table->nullableMorphs('rateable');
            $table->decimal('rate', 5, 2); // percent, e.g. 10.00
            $table->string('name', 120)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['kind', 'starts_on', 'ends_on']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('currency', 3)->default('PHP');
            $table->decimal('pending_balance', 14, 2)->default(0);
            $table->decimal('available_balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained('payments')->cascadeOnDelete();
            $table->foreignId('commission_rate_id')->nullable()->constrained('commission_rates')->nullOnDelete();
            $table->decimal('gross', 12, 2);
            $table->decimal('rate', 5, 2);
            $table->decimal('platform_fee', 12, 2);
            $table->decimal('host_amount', 12, 2);
            $table->string('status', 20)->default('pending'); // pending | released | reversed
            $table->timestamp('released_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('requested'); // requested | paid | rejected
            $table->string('method', 30); // bank | gcash | maya
            $table->string('account_name', 120);
            $table->string('account_number', 60);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type', 30); // earning | release | reversal | payout | payout_reversal
            $table->string('bucket', 10); // pending | available
            $table->decimal('amount', 14, 2); // signed
            $table->decimal('balance_after', 14, 2);
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('payouts')->nullOnDelete();
            $table->string('description');
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('commission_rates');
    }
};
