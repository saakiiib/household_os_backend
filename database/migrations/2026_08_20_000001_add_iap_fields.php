<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add platform product IDs to subscription_plans
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('apple_product_id')->nullable()->after('slug');
            $table->string('google_product_id')->nullable()->after('apple_product_id');
        });

        // Add Apple + Google fields to subscriptions
        Schema::table('subscriptions', function (Blueprint $table) {
            // Apple IAP fields
            $table->string('apple_product_id')->nullable()->after('paypal_subscription_id');
            $table->string('apple_original_transaction_id')->nullable()->after('apple_product_id');
            $table->string('apple_receipt_data')->nullable()->after('apple_original_transaction_id');
            // Google Play IAP fields
            $table->string('google_product_id')->nullable()->after('apple_receipt_data');
            $table->string('google_order_id')->nullable()->after('google_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['apple_product_id', 'google_product_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'apple_product_id', 'apple_original_transaction_id', 'apple_receipt_data',
                'google_product_id', 'google_order_id',
            ]);
        });
    }
};
