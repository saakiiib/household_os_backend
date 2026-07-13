<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->enum('frequency', ['monthly', 'quarterly', 'annual', 'one-time'])->default('annual');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('category')->nullable();
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'status']);
            $table->index(['household_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewals');
    }
};
