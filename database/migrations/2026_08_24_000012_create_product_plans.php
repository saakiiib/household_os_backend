<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_plans: normalized plan <-> store product mapping (command.txt §14).
     */
    public function up(): void
    {
        Schema::create('product_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('provider')->default('apple');
            $table->string('product_id'); // e.g. com.mentosoftware.householdos.complete.monthly
            $table->string('billing_period'); // monthly | annual
            $table->unsignedTinyInteger('level')->default(3);
            $table->timestamps();

            $table->unique(['provider', 'product_id']);
            $table->index(['plan_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_plans');
    }
};
