<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('stripe_price_monthly')->nullable()->after('google_product_id');
            $table->string('stripe_price_annual')->nullable()->after('stripe_price_monthly');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['stripe_price_monthly', 'stripe_price_annual']);
        });
    }
};
