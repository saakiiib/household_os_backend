<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * subscription_transactions: full Apple transaction history (command.txt §16).
     * Every renewal appends a new row — the previous transaction is never
     * overwritten.
     */
    public function up(): void
    {
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('transaction_id')->index();
            $table->string('original_transaction_id')->nullable()->index();
            $table->string('product_id')->nullable();
            $table->string('transaction_reason')->nullable();
            $table->string('environment')->nullable();
            $table->timestamp('purchase_date')->nullable();
            $table->timestamp('expires_date')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
