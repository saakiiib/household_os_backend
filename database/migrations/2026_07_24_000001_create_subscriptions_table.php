<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who created/purchased
            $table->foreignId('household_id')->constrained()->cascadeOnDelete(); // which household owns this
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('trial');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('paypal_subscription_id')->nullable();
            $table->timestamps();

            $table->unique('household_id'); // one subscription per household
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
