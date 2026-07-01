<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('title');
            $table->enum('category', ['insurance', 'passport', 'subscription', 'warranty', 'contract', 'medical', 'other']);
            $table->date('renewal_date');
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->foreignId('responsible_user_id')->constrained('users');
            $table->enum('frequency', ['annual', 'bi-annual', 'quarterly', 'monthly', 'one-time']);
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled', 'renewed'])->default('active');
            // document_id will be added after documents table migration in Phase 5
            $table->boolean('reminder_sent_90d')->default(false);
            $table->boolean('reminder_sent_30d')->default(false);
            $table->boolean('reminder_sent_7d')->default(false);
            $table->boolean('reminder_sent_due')->default(false);
            $table->timestamps();

            $table->index('renewal_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewals');
    }
};
