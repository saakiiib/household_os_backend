<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who made the payment
            $table->foreignId('household_id')->constrained()->cascadeOnDelete(); // which household
            $table->foreignId('subscription_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('currency', 10)->default('gbp');
            $table->string('payment_method');
            $table->string('gateway');
            $table->string('gateway_payment_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'status']);
            $table->index('gateway_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
