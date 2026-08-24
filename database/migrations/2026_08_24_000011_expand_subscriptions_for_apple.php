<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand the subscriptions table to fully track Apple's subscription
     * lifecycle (command.txt §15).
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('subscriber_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->string('provider')->nullable()->after('payment_method')->default('apple');
            $table->string('product_id')->nullable()->after('provider');
            $table->string('billing_period')->nullable()->after('product_id');
            $table->string('original_transaction_id')->nullable()->after('billing_period');
            $table->string('latest_transaction_id')->nullable()->after('original_transaction_id');
            $table->string('environment')->nullable()->after('latest_transaction_id'); // Production / Sandbox
            $table->boolean('auto_renew')->default(true)->after('environment');
            $table->string('app_account_token')->nullable()->after('auto_renew');
            $table->timestamp('grace_period_expires_at')->nullable()->after('cancelled_at');
            $table->timestamp('expired_at')->nullable()->after('grace_period_expires_at');
            $table->timestamp('revoked_at')->nullable()->after('expired_at');
            $table->timestamp('last_verified_at')->nullable()->after('revoked_at');

            $table->index('original_transaction_id');
            $table->index('latest_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['original_transaction_id']);
            $table->dropIndex(['latest_transaction_id']);
            $table->dropColumn([
                'subscriber_user_id',
                'provider',
                'product_id',
                'billing_period',
                'original_transaction_id',
                'latest_transaction_id',
                'environment',
                'auto_renew',
                'app_account_token',
                'grace_period_expires_at',
                'expired_at',
                'revoked_at',
                'last_verified_at',
            ]);
        });
    }
};
