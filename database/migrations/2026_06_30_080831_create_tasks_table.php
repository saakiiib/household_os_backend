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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('task_type', ['one-time', 'recurring', 'rotating'])->default('one-time');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'on_hold'])->default('pending');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly'])->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->integer('reward_points')->default(0)->nullable();
            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('color', 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
